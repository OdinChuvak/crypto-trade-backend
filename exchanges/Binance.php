<?php

namespace app\exchanges;

use app\clients\CurlClient;
use app\exceptions\ApiException;
use app\enums\AppError;
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
            '-1099' => AppError::NO_AUTH_KEY_FILE,
            '-2014' => AppError::WRONG_API_KEY,
            '-1022' => AppError::INCORRECT_SIGNATURE,
            'KEY_IS_NOT_ACTIVATED' => AppError::KEY_IS_NOT_ACTIVATED,
            '-1100' => AppError::PARAMETER_ERROR,
            '-1101' => AppError::PARAMETER_ERROR,
            '-1102' => AppError::PARAMETER_ERROR,
            '-1103' => AppError::PARAMETER_ERROR,
            '-1104' => AppError::PARAMETER_ERROR,
            '-1105' => AppError::PARAMETER_ERROR,
            '-1106' => AppError::PARAMETER_ERROR,
            'HEADER_KEY_IS_NOT_FIND' => AppError::HEADER_KEY_IS_NOT_FIND,
            '-1003' => AppError::REQUESTS_LIMIT_IS_EXCEEDED,
            '-5002' => AppError::INSUFFICIENT_FUNDS,
            'QUANTITY_LESS' => AppError::QUANTITY_LESS,
            'QUANTITY_MORE' => AppError::QUANTITY_MORE,
            'PRICE_LESS' => AppError::PRICE_LESS,
            'PRICE_MORE' => AppError::PRICE_MORE,
            'AMOUNT_LESS' => AppError::AMOUNT_LESS,
            'AMOUNT_MORE' => AppError::AMOUNT_MORE,
            '-2013' => AppError::ORDER_NOT_FOUND,
            'ORDER_CREATION_PROBLEM' => AppError::ORDER_CREATION_PROBLEM,
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

            $pairSets = [];

            foreach ($exchangeCurrencyPair['filters'] as $filter) {

                if ($filter['filterType'] === 'LOT_SIZE') {
                    $pairSets['minQty'] = $filter['minQty'];
                    $pairSets['maxQty'] = $filter['maxQty'];
                    $pairSets['stepSize'] = $filter['stepSize'];
                }

                if ($filter['filterType'] === 'PRICE_FILTER') {
                    $pairSets['minPrice'] = $filter['minPrice'];
                    $pairSets['maxPrice'] = $filter['maxPrice'];
                    $pairSets['tickSize'] = $filter['tickSize'];
                }

                if ($filter['filterType'] === 'MIN_NOTIONAL') {
                    $pairSets['minNotional'] = $filter['minNotional'];
                }
            }

            $currencyPairsList[] = [
                'first_currency' => $exchangeCurrencyPair['baseAsset'],
                'second_currency' => $exchangeCurrencyPair['quoteAsset'],
                'min_quantity' => $pairSets['minQty'],
                'max_quantity' => $pairSets['maxQty'],
                'quantity_step' => $pairSets['stepSize'],
                'min_price' => $pairSets['minPrice'],
                'max_price' => $pairSets['maxPrice'],
                'price_step' => $pairSets['tickSize'],
                'min_amount' => $pairSets['minNotional'] ?? null,
                'max_amount' => $pairSets['maxNotional'] ?? null,
                'price_precision' => $exchangeCurrencyPair['quoteAssetPrecision'],
                'quantity_precision' => $exchangeCurrencyPair['baseAssetPrecision'],
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
     * @throws ApiException
     */
    public function getCommissions(Pair $pair = null): array
    {
        $params = $pair ? [
            "symbol" => $pair->first_currency . $pair->second_currency,
        ] : null;

        $apiResult = $this->sendPrivateQuery('asset/tradeFee', $params, "GET", "SAPI");
        $commissions = [];

        foreach($apiResult as $commission) {
            $commissions[] = [
                'commission_taker_percent' => $commission['takerCommission'] * 100,
                'commission_maker_percent' => $commission['makerCommission'] * 100,
            ];
        }

        return $commissions;
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