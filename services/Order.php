<?php

namespace app\services;

use app\models\ExchangePair;
use app\utils\Math;

class Order
{
    public static function getQuantity(?\app\models\Order $order): ?float
    {
        /**
         * Берем ID предыдущего ордера на продажу
         */
        $lastSellOrderId = \app\models\Order::find()
            ->select('id')
            ->where([
                'trading_line_id' => $order->trading_line_id,
                'operation' => 'sell',
                'is_executed' => 1,
            ])
            ->orderBy(['id' => SORT_DESC])
            ->limit(1)
            ->scalar();

        /**
         * Получаем все исполненные ордера на покупку после последней продажи
         */
        $lastBuyOrders = \app\models\Order::find()
            ->where([
                'trading_line_id' => $order->trading_line_id,
                'operation' => 'buy',
                'is_executed' => 1,
            ])
            ->andWhere(['>', 'id', $lastSellOrderId])
            ->all();

        /**
         * Если еще ничего не было куплено после последней продажи, вернем количество,
         * рассчитанное на начальных данных
         */
        if (!$lastBuyOrders) {
            return self::numberValueNormalization(
                $order->line->first_order_amount / $order->required_rate,
                $order->exchangePair->quantity_precision,
                $order->exchangePair->quantity_step
            );
        }

        /**
         * Если исходный ордер является ордером на продажу, то вернем объем покупки
         */
        if ($order->operation === 'sell') {
            return self::numberValueNormalization(
                array_sum(array_column($lastBuyOrders, 'received')),
                $order->exchangePair->quantity_precision,
                $order->exchangePair->quantity_step
            );
        }

        /**
         * Берем стандартную прибыль по линии
         */
        $oneStepIncome = ($order->line->first_order_amount / 100) * $order->line->sell_rate_step;

        /**
         * Если исходный ордер является ордером на покупку, то сначала рассчитаем курс,
         * по которой будем продавать текущий ордер в перспективе
         */
        $sell_rate = $order->required_rate + Math::getPercent($order->required_rate, $order->line->sell_rate_step);

        /**
         * Вычисляем вложенную в предыдущие ордера на покупку сумму и полученное количество
         */
        $investedAmount = 0;
        $receivedQuantity = 0;

        foreach ($lastBuyOrders as $buyOrder) {
            $investedAmount += $buyOrder->invested;
            $receivedQuantity += $buyOrder->received;
        }

        /**
         * Вычисляем убыток, который образуется от продажи $receivedQuantity по курсу $sell_rate
         */
        $loss = $investedAmount - $receivedQuantity * $sell_rate;

        /**
         * Вычисляем количество, необходимое для покрытия убытка и получения нужной прибыли
         */
        $needQuantity = ($oneStepIncome + $loss) / ($sell_rate - $order->required_rate);

        return self::numberValueNormalization(
            $needQuantity,
            $order->exchangePair->quantity_precision,
            $order->exchangePair->quantity_step
        );
    }

    /**
     * Пытается создать ордер для предыдущего ордера с учетом допустимых рамок для курса в валютной паре
     *
     * @param \app\models\Order $previousOrder
     * @param $order_type
     * @return bool
     */
    public static function createOrder(\app\models\Order $previousOrder, $order_type, $order_options = []): bool
    {
        /**
         * Получим данные по валютной паре ордера
         */
        $pair = ExchangePair::findOne([
            'pair_id' => $previousOrder->line->pair_id,
            'exchange_id' => $previousOrder->line->exchange_id,
        ]);

        /**
         * Рассчитаем курс для текущей операции
         */
        $order_rate = $order_type === 'buy'
            ? (100 * $previousOrder->actual_rate) / (100 + $previousOrder->line->buy_rate_step)
            : (1 + ($previousOrder->line->sell_rate_step / 100)) * $previousOrder->actual_rate;

        $order_data = [
            'user_id' => $previousOrder->user_id,
            'trading_line_id' => $previousOrder->trading_line_id,
            'operation' => $order_type,
            'required_rate' => self::numberValueNormalization($order_rate, $pair->price_precision, $pair->price_step),
        ];

        \app\models\Order::add(array_merge($order_data, $order_options), '');

        return true;
    }

    /**
     * Метод нормализует значения:
     *  - округляет до знаков $precision после запятой,
     *  - делает значение кратным $step.
     *
     * @param float $value
     * @param int $precision
     * @param float $step
     * @return float
     */
    public static function numberValueNormalization(float $value, int $precision, float $step): float
    {
        $fmod = $step ? fmod($value, $step) : 0;

        return round($value - $fmod, $precision);
    }
}