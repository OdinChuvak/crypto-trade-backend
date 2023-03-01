<?php

namespace app\services;

use app\enums\AppError;
use app\exchanges\ExchangeInterface;
use app\models\ExchangeRate;
use app\models\Notice;
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

    public static function checkPairRate(?ExchangeRate $exchangeRate, \app\models\TradingLine $line): bool
    {
        $isCheck = $exchangeRate && (time() - strtotime($exchangeRate->updated_at)) <= ExchangeRate::ACTUAL_RATE_TIME;

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