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
        $previousOrder = $order->previous;

        /* Если предыдущий ордер не задан, вернем стандартное количество для линии */
        if (!$previousOrder) {
            return $line->order_amount / $order->required_trading_rate;
        }

        /* Если предыдущий ордер есть, при этом операции у них равны,
         * значит, что-то пошло не так. Вернем null,
         * чтобы вызвать ошибку запроса */
        if ($order->operation === $previousOrder->operation) {
            return null;
        }

        /* Если задан первый тип торговли - торговать на все */
        if ($line->trading_method === 1) {
            return $order->operation === 'buy'
                ? $previousOrder->received / $order->required_trading_rate
                : $previousOrder->received;
        }

        /* Если задан второй тип торговли - копить первую валюту */
        elseif ($line->trading_method === 2) {
            return $order->operation === 'buy'
                ? $previousOrder->received / $order->required_trading_rate
                : $line->order_amount / $previousOrder->required_trading_rate;
        }

        /* Если задан второй тип торговли - копить вторую валюту */
        elseif ($line->trading_method === 3) {
            return $order->operation === 'buy'
                ? $line->order_amount / $order->required_trading_rate
                : $previousOrder->received;
        }

        else {
            return null;
        }
    }
}