<?php

namespace app\services;

use app\enums\AppError;
use app\exchanges\ExchangeInterface;
use app\models\ExchangeRate;
use app\models\MarketDynamic;
use app\models\Notice;
use app\models\Pair;
use Exception;

class TradingLine
{
    /**
     * @throws Exception
     */
    public static function updateCommission(ExchangeInterface $exchange, \app\models\TradingLine $line): bool
    {
        /**
         * Получаем новые данные по комиссии на торговой линии
         */
        $commission = $exchange->getCommissions();

        /**
         * Обновляем данные по комиссии в БД
         */
        $line->load($commission[0], '');

        return $line->save();
    }

    public static function checkPairRate(\app\models\TradingLine $line): bool
    {
        $exchangeRate = $line->exchangeRate;
        $isCheck = $exchangeRate && (time() - strtotime($exchangeRate->created_at)) <= ExchangeRate::ACTUAL_RATE_TIME;

        if (!$isCheck) {
            Notice::add([
                'user_id' => $line->user_id,
                'reference' => 'trading_line',
                'reference_id' => $line->id,
                'type' => AppError::OUTDATED_RATE['type'],
                'message' => AppError::OUTDATED_RATE['message'],
                'error_code' => AppError::OUTDATED_RATE['code'],
            ]);
        }

        return $isCheck;
    }

    public static function isBestTimeForPlacement(\app\models\Order $order): bool
    {
        /**
         * Самый актуальный курс валютной пары линии
         */
        $actualRate = ExchangeRate::find()
            ->where([
                'pair_id' => $order->line->pair_id,
                'exchange_id' => $order->line->exchange_id,
            ])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        /**
         * Если курс не достиг нужного порога.
         * Для выставления ордера на покупку курс должен быть < Order::required_rate
         * Для выставления ордера на продажу курс должен быть > Order::required_rate
         */
        if (($order->operation === 'buy' && $order->required_rate < $actualRate->value)
            || ($order->operation === 'sell' && $order->required_rate > $actualRate->value)) return false;

        /**
         * Если для ордера задано легкое размещение (без условий, кроме достижения нужного порога)
         */
        if ($order->is_easy_placement) return true;

        /** Если динамика рынка отрицательная, ордера на покупку не выставляем */
        if ($order->operation === 'buy' && MarketService::isNegativeMarketDynamic($order->line))
            return false;

        /**
         * Возьмем последний курс, который не достиг нужного порога Order::required_rate
         */
        $lastOutsideRate = ExchangeRate::find()
            ->where([
                'pair_id' => $order->line->pair_id,
                'exchange_id' => $order->line->exchange_id,
            ])
            ->andWhere([$order->operation === 'buy' ? '>' : '<', 'value', $order->required_rate])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        /**
         * Если происходит такое, то можно смело выкладывать ордер, так как курс достиг нужного порога
         */
        if (!$lastOutsideRate) return true;

        /**
         * Определяем минимальный (для покупки) или максимальный (для продажи) курс после прохождения порога required_rate
         */
        $condition = $lastOutsideRate
            ? ['>', 'created_at', $lastOutsideRate->created_at]
            : [$order->operation === 'buy' ? '<' : '>', 'value', $order->required_rate];

        $extremumRate = ExchangeRate::find()
            ->where([
                'pair_id' => $order->line->pair_id,
                'exchange_id' => $order->line->exchange_id,
            ])
            ->andWhere($condition)
            ->orderBy(['value' => $order->operation === 'buy' ? SORT_ASC : SORT_DESC])
            ->one();

        /**
         * Если не удалось определить требуемые курсы (например, если их нет в БД)
         */
        if (!$actualRate || !$extremumRate) return false;

        /**
         * Насчитываем спред между Order::required_rate и экстремумом, а также спред между экстремумом и текущим курсом
         */
        $spreadRequiredExtremum = abs($order->required_rate - $extremumRate->value);
        $spreadRequiredExtremumInPercent = ($spreadRequiredExtremum / $order->required_rate) * 100;
        $spreadActualExtremum = abs($actualRate->value - $extremumRate->value);

        /**
         * Определяем необходимую величину отскока для выставления ордера (в % от спреда)
         */
        if ($order->operation === 'buy') {
            match (true) {
                $spreadRequiredExtremumInPercent <= 1 => $rebound = 50,
                $spreadRequiredExtremumInPercent <= 3 => $rebound = 30,
                $spreadRequiredExtremumInPercent <= 10 => $rebound = 10,
                default => $rebound = 0,
            };
        } elseif ($order->operation === 'sell') {
            $rebound = 0;
        }

        /**
         * Если динамика курса достигла нужной величины отскока, вернем true и запишем лог
         */
        if (($spreadActualExtremum / $spreadRequiredExtremum) * 100 >= $rebound) {
            \Yii::info([
                'order' => [
                    'id' => $order->id,
                    'operation' => $order->operation,
                    'required_rate' => $order->required_rate,
                ],
                'lastOutsideRate' => [
                    'id' => $lastOutsideRate->id,
                    'value' => $lastOutsideRate->value,
                ],
                'extremumRate' => [
                    'id' => $extremumRate->id,
                    'value' => $extremumRate->value,
                ],
                'countedValues' => [
                    'spreadRequiredExtremum' => $spreadRequiredExtremum,
                    'spreadActualExtremum' => $spreadActualExtremum,
                    'spreadRequiredExtremumInPercent' => $spreadRequiredExtremumInPercent,
                    'rebound' => $rebound,
                ],
            ], 'isBestTimeForPlacement');

            return true;
        }

        return false;
    }

    /**
     * Проверяет, разрешено ли создавать ордер на покупку
     *
     * @param \app\models\TradingLine $line
     * @return true
     */
    public static function checkBuyOrderLimit(\app\models\TradingLine $line): bool
    {
        /**
         * Если не задан лимит на количество ордеров на покупку, вернем true
         */
        if (!$line->buy_order_limit) {
            return true;
        }

        /**
         * Если лимит задан, возмем количество ордеров на покупку
         * среди последних N(\app\models\TradingLine::buy_order_limit) исполненных ордеров
         * и сравним с заданным лимитом
         */
        $lastExecutionOrderIds = \app\models\Order::find()
            ->select('id')
            ->where([
                '`order`.`is_executed`' => true,
                '`order`.`trading_line_id`' => $line->id
            ])
            ->limit($line->buy_order_limit)
            ->orderBy(['`order`.`executed_at`' => SORT_DESC])
            ->column();

        $numberOfBuyOrdersAmongTheLatest = \app\models\Order::find()
            ->select('COUNT(*)')
            ->where([
                '`order`.`id`' => $lastExecutionOrderIds,
                '`order`.`operation`' => 'buy'
            ])
            ->groupBy('operation')
            ->scalar();

        /**
         * Если количество ордеров на покупку среди последних N меньше лимита,
         * вернем true (разрешаем создавать новые ордера на покупку)
         */
        if ($line->buy_order_limit > $numberOfBuyOrdersAmongTheLatest) {
            return true;
        }

        return false;
    }
}