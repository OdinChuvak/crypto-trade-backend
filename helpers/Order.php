<?php

namespace app\helpers;

use app\models\ExchangeCurrencyPair;
use app\models\TradingLineLog;

class Order
{
    public static function getQuantity($order_id): ?float
    {
        $order = \app\models\Order::find()
            ->with(['line', 'continued'])
            ->where(['id' => $order_id])
            ->one();

        $line = $order->line;
        $continuedOrder = $order->continued;

        /**
         * Если предыдущий ордер не задан, или курс предыдущего ордера не корректен
         * (если предыдущий ордер на покупку, а текущий на продажу, но курс текущего меньше предыдущего, и наоборот),
         * вернем стандартное количество для линии
         */
        if (!$continuedOrder
            || ($continuedOrder->operation === 'buy' && $continuedOrder->actual_trading_rate >= $order->required_trading_rate)
            || ($continuedOrder->operation === 'sell' && $continuedOrder->actual_trading_rate <= $order->required_trading_rate))
            return $line->amount / $order->required_trading_rate;

        /**
         * Если предыдущий ордер есть, при этом операции у них равны,
         * значит, что-то пошло не так. Вернем null,
         * чтобы вызвать ошибку запроса
         */
        if ($order->operation === $continuedOrder->operation) {
            return null;
        }

        /**
         * Если задан первый тип торговли - торговать на все
         */
        if ($line->trading_method === 1) {
            return $order->operation === 'buy'
                ? $continuedOrder->received / $order->required_trading_rate
                : $continuedOrder->received;
        }

        /**
         * Если задан второй тип торговли - копить первую валюту
         */
        elseif ($line->trading_method === 2) {
            return $order->operation === 'buy'
                ? $continuedOrder->received / $order->required_trading_rate
                : $line->amount / $order->required_trading_rate;
        }

        /**
         * Если задан второй тип торговли - копить вторую валюту
         */
        elseif ($line->trading_method === 3) {
            return $order->operation === 'buy'
                ? $line->amount / $order->required_trading_rate
                : $continuedOrder->received;
        }

        else {
            return null;
        }
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
        $pair = ExchangeCurrencyPair::findOne([
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
            $continuedOrderForBuy = self::getContinuedOrder('buy', $previousOrder->id);

            \app\models\Order::add([
                'user_id' => $previousOrder->user_id,
                'trading_line_id' => $previousOrder->trading_line_id,
                'previous_order_id' => $previousOrder->id,
                'continued_order_id' => $continuedOrderForBuy?->id,
                'operation' => $order_type,
                'required_trading_rate' => $order_rate,
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