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
            return round($order->line->first_order_amount / $order->required_rate, $order->exchangePair->quantity_precision);
        }

        /**
         * Если исходный ордер является ордером на продажу, то вернем объем покупки
         */
        if ($order->operation === 'sell') {
            return round(array_sum(array_column($lastBuyOrders, 'received')), $order->exchangePair->quantity_precision);
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
        
        return round(($income + $amountSum - $quantityRateSum) / ($sell_rate - $rate), $order->exchangePair->quantity_precision);
    }

    /**
     * В таблице ордеров есть 2 вида подордеров: previous_order_id и continued_order_id
     *
     * previous_order_id - это ордер, продолжение которого породило текущий. То есть, после исполнения этого ордера,
     * в ответ были созданы 2 последующих за ним ордера
     *
     * continued_order_id - это ордер, ответом на который, с точки зрения средств, является текущий. Например,
     * для ордера продажи - это предыдущий "свободный" ордер покупки, и наоборот
     *
     * @param string $current_order_operation - операция ордера, для которого ищем continuedOrder
     * @param int $previous_order_id - id предыдущего ордера
     * @return \app\models\Order|null
     */
    public static function getContinuedOrder(string $current_order_operation, int $previous_order_id): ?\app\models\Order
    {
        $current_graph_order_id = $previous_order_id;
        $continuedOrderIds = [];

        while ($current_graph_order_id) {
            $currentGraphOrder = \app\models\Order::findOne($current_graph_order_id);

            if ($currentGraphOrder->operation === $current_order_operation) {
                if (!$currentGraphOrder->continued_order_id) {
                    return null;
                }

                $continuedOrderIds[] = $currentGraphOrder->continued_order_id;
            } elseif (!in_array($currentGraphOrder->id, $continuedOrderIds)) {
                return $currentGraphOrder;
            }

            $current_graph_order_id = $currentGraphOrder->previous_order_id;
        }

        return null;
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
        $order_rate = $order_type === 'buy' ? round((100 * $previousOrder->actual_trading_rate) / (100 + $previousOrder->line->exchange_rate_step), $pair->price_precision)
            : round((1 + ($previousOrder->line->exchange_rate_step / 100)) * $previousOrder->actual_trading_rate, $pair->price_precision);

        /**
         * Если курс в допустимых рамках, создадим ордер
         */
        if ($order_rate >= $pair->limits->lower_limit && $order_rate <= $pair->limits->upper_limit) {
            $continuedOrder = self::getContinuedOrder($order_type, $previousOrder->id);

            \app\models\Order::add([
                'user_id' => $previousOrder->user_id,
                'trading_line_id' => $previousOrder->trading_line_id,
                'previous_order_id' => $previousOrder->id,
                'continued_order_id' => $continuedOrder?->id,
                'operation' => $order_type,
                'required_rate' => $order_rate,
            ], '');

        } else {

            /**
             * Если курс вышел за допустимые рамки, пишем лог
             */
            TradingLineLog::add([
                'user_id' => $previousOrder->user_id,
                'trading_line_id' => $previousOrder->line->id,
                'type' => 'warning',
                'message' => 'Ордер' . $order_type === 'buy' ? 'на покупку' : 'на продажу' . ' не создан, так как курс ордера вышел за допустимые границы!',
                'error_code' => null,
            ]);

            return false;
        }

        return true;
    }
}