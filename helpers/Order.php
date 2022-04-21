<?php

namespace app\helpers;

class Order
{
    public static function getQuantity($order_id): ?float
    {
        $order = \app\models\Order::find()
            ->with(['line', 'previous'])
            ->where(['id' => $order_id])
            ->one();

        $line = $order->line;
        $continuedOrder = $order->continued;

        /* Если предыдущий ордер не задан, вернем стандартное количество для линии */
        if (!$continuedOrder) {
            return $line->amount / $order->required_trading_rate;
        }

        /* Если предыдущий ордер есть, при этом операции у них равны,
         * значит, что-то пошло не так. Вернем null,
         * чтобы вызвать ошибку запроса */
        if ($order->operation === $continuedOrder->operation) {
            return null;
        }

        /* Если задан первый тип торговли - торговать на все */
        if ($line->trading_method === 1) {
            return $order->operation === 'buy'
                ? $continuedOrder->received / $order->required_trading_rate
                : $continuedOrder->received;
        }

        /* Если задан второй тип торговли - копить первую валюту */
        elseif ($line->trading_method === 2) {
            return $order->operation === 'buy'
                ? $continuedOrder->received / $order->required_trading_rate
                : $line->amount / $continuedOrder->required_trading_rate;
        }

        /* Если задан второй тип торговли - копить вторую валюту */
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
}