<?php

namespace app\mappers;

use app\helpers\AppError;
use app\models\CurrencyPair;
use app\models\Order;

class Exmo implements DataMapperInterface
{
    /**
     * Связь названия API биржи, с методом мапинга данных
     */
    const API_MAP = [
        'pair_settings' => 'mapCurrencyPairsList',
        'user_open_orders' => 'mapOrdersList',
        'order_create' => 'mapOrderData',
    ];

    /**
     * @param $api_name
     * @param $data
     * @return mixed
     */
    public static function mapData($api_name, $data, $payload = [])
    {
        $data = json_decode($data, true);

        if (isset($data['result']))
            return self::mapError($data, $payload);

        if (isset(self::API_MAP[$api_name]))
            return call_user_func(self::class . '::' . self::API_MAP[$api_name], $data, $payload);

        return $data;
    }

    /**
     * @param array $exchangeCurrencyPairsList
     * @return array
     * @inheritDoc
     */
    public static function mapCurrencyPairsList(array $exchangeCurrencyPairsList): array
    {
        $currencyPairsList = [];

        foreach ($exchangeCurrencyPairsList as $pair => $exchangeCurrencyPair) {
            $currencyPair = new CurrencyPair();

            $pair = explode('_', $pair);

            $currencyPair->load([
                'exchange_id' => 1,
                'name' => $pair[0].'/'.$pair[1],
                'first_currency' => $pair[0],
                'second_currency' => $pair[1],
                'min_quantity' => $exchangeCurrencyPair['min_quantity'],
                'max_quantity' => $exchangeCurrencyPair['max_quantity'],
                'min_price' => $exchangeCurrencyPair['min_price'],
                'max_price' => $exchangeCurrencyPair['max_price'],
                'min_amount' => $exchangeCurrencyPair['min_amount'],
                'max_amount' => $exchangeCurrencyPair['max_amount'],
                'price_precision' => $exchangeCurrencyPair['price_precision'],
                'commission_taker_percent' => $exchangeCurrencyPair['commission_taker_percent'],
                'commission_maker_percent' => $exchangeCurrencyPair['commission_maker_percent'],
            ], '');

            $currencyPairsList[] = $currencyPair;
        }

        return $currencyPairsList;
    }

    public static function mapOrdersList($ordersList)
    {
        return $ordersList;
    }

    public static function mapOrderData($orderData, $payload): ?Order
    {
        $order = Order::findOne($payload['order_id']);

        $order->load([
            'exchange_order_id' => $orderData['order_id'],
        ], '');

        return $order;
    }

    public static function mapError($data): array
    {
        return [
            'error_code' => AppError::getExchangeErrorFromMessage($data['error']),
            'error_message' => $data['error'],
        ];
    }
}