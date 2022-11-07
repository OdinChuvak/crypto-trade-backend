<?php

namespace app\exchanges;

use app\clients\CurlClient;
use app\exceptions\ApiException;
use app\helpers\AppError;
use app\models\Order;
use app\models\Pair;
use Exception;

class Binance extends BaseExchange implements ExchangeInterface
{
    /**
     * @inheritDoc
     */
    public static function getApiUrl(string $apiKey = "REST"): string
    {
        if ($apiKey === 'SAPI') {
            return 'https://api.binance.com/sapi/v1/';
        }

        return 'https://api.binance.com/api/v3/';
    }

    /**
     * @inheritDoc
     */
    public static function getExchangeErrorMap(): array
    {
        return [
            '-1022' => AppError::INCORRECT_SIGNATURE,
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getExchangeErrorCode(array $errorData): int
    {
        return $errorData['code'];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public static function getTicker(): array
    {
        $exchangeTicker = self::sendPublicQuery('ticker/price');
        $ticker = [];
        $pair = null;

        foreach ($exchangeTicker as $data) {

            for ($i = 1; $i < strlen($data['symbol']); $i++) {
                $pair = Pair::findOne([
                    'first_currency' => substr($data['symbol'], 0, $i),
                    'second_currency' => substr($data['symbol'], $i),
                ]);

                if ($pair) break;
            }

            if ($pair) {
                $ticker[] = [
                    'first_currency' => $pair->first_currency,
                    'second_currency' => $pair->second_currency,
                    'exchange_rate' => $data['price'],
                ];
            }

        }

        return $ticker;
    }

    /**
     * @inheritDoc
     * @throws ApiException
     */
    public function createOrder(Pair $pair, float $quantity, float $price, string $operation): array
    {
        $apiResult = $this->sendPrivateQuery('order', [
            'symbol' => $pair->first_currency . $pair->second_currency,
            'quantity' => $quantity,
            'price' => $price,
            'type' => 'LIMIT',
            'side' => $operation,
            'timeInForce' => 'GTC',
        ]);

        return [
            'exchange_order_id' => $apiResult['orderId']
        ];
    }

    /**
     * @inheritDoc
     * @throws ApiException
     */
    public function cancelOrder(Order $order)
    {
        return $this->sendPrivateQuery('order', [
            'orderId' => $order->exchange_order_id,
            'symbol' => $order->pair->first_currency . $order->pair->second_currency,
        ], "DELETE");
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public static function getCurrencyPairsList(): array
    {
        $exchangeInfo = self::sendPublicQuery('exchangeInfo');
        $exchangeCurrencyPairsList = $exchangeInfo['symbols'];
        $currencyPairsList = [];

        foreach ($exchangeCurrencyPairsList as $exchangeCurrencyPair) {

            foreach ($exchangeCurrencyPair['filters'] as $filter) {

                if ($filter['filterType'] === 'LOT_SIZE') {
                    $exchangeCurrencyPair['filterData']['minQty'] = $filter['minQty'];
                    $exchangeCurrencyPair['filterData']['maxQty'] = $filter['maxQty'];
                }

                if ($filter['filterType'] === 'PRICE_FILTER') {
                    $exchangeCurrencyPair['filterData']['minPrice'] = $filter['minPrice'];
                    $exchangeCurrencyPair['filterData']['maxPrice'] = $filter['maxPrice'];
                }

                if ($filter['filterType'] === 'MIN_NOTIONAL') {
                    $exchangeCurrencyPair['filterData']['minNotional'] = $filter['minNotional'];
                }

                if ($filter['filterType'] === 'MAX_NOTIONAL') {
                    $exchangeCurrencyPair['filterData']['maxNotional'] = $filter['maxNotional'];
                }
            }

            $currencyPairsList[] = [
                'first_currency' => $exchangeCurrencyPair['baseAsset'],
                'second_currency' => $exchangeCurrencyPair['quoteAsset'],
                'min_quantity' => $exchangeCurrencyPair['filterData']['minQty'],
                'max_quantity' => $exchangeCurrencyPair['filterData']['maxQty'],
                'min_price' => $exchangeCurrencyPair['filterData']['minPrice'],
                'max_price' => $exchangeCurrencyPair['filterData']['maxPrice'],
                'min_amount' => $exchangeCurrencyPair['filterData']['minNotional'] ?? null,
                'max_amount' => $exchangeCurrencyPair['filterData']['maxNotional'] ?? null,
                'price_precision' => $exchangeCurrencyPair['quoteAssetPrecision'],
                'quantity_precision' => $exchangeCurrencyPair['baseAssetPrecision'],
                'commission_taker_percent' => null,
                'commission_maker_percent' => null,
            ];
        }

        return $currencyPairsList;
    }

    /**
     * @inheritDoc
     * @throws ApiException
     */
    public function getOpenOrdersList(): array
    {
        $apiResult = $this->sendPrivateQuery('openOrders', null, "GET");
        $userOpenOrders = [];

        foreach ($apiResult as $order) {
            $userOpenOrders[] = $order['orderId'];
        }

        return $userOpenOrders;
    }

    /**
     * @inheritDoc
     * @throws ApiException
     */
    public function getOrderTrades(Order $order): array
    {
        $apiResult = $this->sendPrivateQuery('myTrades', [
            'orderId' => $order->exchange_order_id,
            'symbol' => $order->pair->first_currency . $order->pair->second_currency,
        ], "GET");

        $orderTrades = [];

        foreach ($apiResult as $trade) {
            $orderTrades[] = [
                "order_id" => $trade["orderId"],
                "date" => $trade["time"],
                "type" => $trade["isBuyer"] ? 'buy' : 'sell',
                "quantity" => $trade["qty"],
                "price" => $trade["price"],
                "amount" => $trade["quoteQty"],
                "commission_amount" => $trade["commission"],
                "commission_currency" => $trade["commissionAsset"],
            ];
        }

        return $orderTrades;
    }

    /**
     * @inheritDoc
     * @throws ApiException
     * @throws Exception
     */
    public function sendPrivateQuery(string $apiName, array $payload = null, string $method = "POST", string $apiKey = "REST")
    {
        $timestamp = time() * 1000;
        $payload['timestamp'] = $timestamp;
        $queryString = http_build_query($payload, '', '&');

        $sign = hash_hmac('SHA256', $queryString, $this->userKeys->secret);
        $payload['signature'] = $sign;

        // генерируем заголовки
        $headers = [
            'X-MBX-APIKEY: ' . $this->userKeys->public,
        ];

        $params = $method != 'POST' ? ['CURLOPT_POST' => false] : [];

        $res = CurlClient::sendQuery(self::getApiUrl($apiKey) . $apiName, $method, $payload, $headers, $params);
        $dec = json_decode($res, true);

        if ($dec === null) {
            throw new Exception('Получены неверные данные. Убедитесь, что соединение работает и запрошенный API существует.');
        }

        return self::getResponse($dec);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public static function sendPublicQuery(string $apiName, array $payload = null, array $headers = null, array $params = null, string $method = "GET", string $apiKey = "REST")
    {
        $apiData = CurlClient::sendQuery(self::getApiUrl($apiKey) . $apiName, $method, $payload, $headers, $params);
        $apiData = json_decode($apiData, true);

        return self::getResponse($apiData);
    }

    /**
     * @throws ApiException
     */
    public static function getResponse(mixed $apiData)
    {
        // если запрос завершился неудачей, выбросим исключение
        if (isset($apiData['code'])) {
            $error = self::getExchangeErrorMap()[self::getExchangeErrorCode($apiData)];
            throw new ApiException($error['message'], $error['code']);
        }

        // иначе вернем ответ запроса
        return $apiData;
    }
}