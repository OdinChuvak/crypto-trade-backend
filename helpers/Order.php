<?php

namespace app\helpers;

use app\models\ExchangePair;
use app\models\TradingLineLog;
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
         * Получаем все исполненные ордера на покупоку до последней продажи
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
         * Если исходный ордер является ордером на покупку, то тут песня
         *
         * Формула для рассчета количества в этом случае:
         *
         * $x = ($income + ∑$amount + ∑($quantity * $sell_rate)) / ($sell_rate - $rate);
         * 
         *      $income - эталонная прибыль. Рассчитывается на основании первой операции купли/продажи.
         *                  Иными словами - сколько прибыли вышло бы, если бы был исполнен первый ордер на
         *                  покупку и за ним последовал ордер на продажу того, что куплено.
         *
         *      $amount - инвестированные средства в ордер на покупку
         * 
         *      $quantity - полученное количество в результате исполнения ордера на покупку
         * 
         *      $rate - курс текущего ордера на покупку
         * 
         *      $sell_rate - курс для продажи ордера
         */

        /**
         * Вычисляем $income
         */
        $firstBuyOrder = $lastBuyOrders[0];

        /**
         * Тут рассчитываем эталонную величину прибыли
         */
        $rateForSellFirstBuyOrder = $firstBuyOrder->required_rate + Math::getPercent($firstBuyOrder->required_rate, $order->line->sell_rate_step);
        $amountForSellFirstBuyOrder = $rateForSellFirstBuyOrder * $firstBuyOrder->received;
        $income = $amountForSellFirstBuyOrder - $firstBuyOrder->invested - $order->line->commission_maker_percent;

        /**
         * Рассчитываем остальные параметры формулы
         */
        $rate = $order->required_rate;
        $sell_rate = $rate + Math::getPercent($rate, $order->line->sell_rate_step);
        $amountSum = 0;
        $quantityRateSum = 0;
        
        foreach ($lastBuyOrders as $buyOrder) {
            $amountSum += $buyOrder->invested;
            $quantityRateSum += $buyOrder->received * $sell_rate;
        }

        return self::numberValueNormalization(
            ($income + $amountSum - $quantityRateSum) / ($sell_rate - $rate),
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
    public static function createOrder(\app\models\Order $previousOrder, $order_type): bool
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

        \app\models\Order::add([
            'user_id' => $previousOrder->user_id,
            'trading_line_id' => $previousOrder->trading_line_id,
            'operation' => $order_type,
            'required_rate' => self::numberValueNormalization($order_rate, $pair->price_precision, $pair->price_step),
        ], '');

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