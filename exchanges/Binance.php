<?php

namespace app\exchanges;

use app\clients\CurlClient;
use app\exceptions\ApiException;
use app\helpers\AppError;
use app\models\Pair;
use Exception;

class Binance extends BaseExchange implements ExchangeInterface
{
    /**
     * @inheritDoc
     */
    public static function getApiUrl(string $apiKey = 'rest'): string
    {
        if ($apiKey === 'sapi') {
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
     */
    public static function getTicker(): array
    {
        // TODO: Implement getTicker() method.
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
     */
    public function cancelOrder(int $exchangeOrderId)
    {
        // TODO: Implement cancelOrder() method.
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public static function getCurrencyPairsList(): array
    {
        $exchangeInfo = self::sendPublicQuery('exchangeInfo', []);
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
                'commission_taker_percent' => null,
                'commission_maker_percent' => null,
            ];
        }

        return $currencyPairsList;
    }

    public function getCommission($pair)
    {
        $exchangeInfo = $this->sendPrivateQuery('exchangeInfo', []);
    }

    /**
     * @inheritDoc
     */
    public function getOpenOrdersList(): array
    {
        // TODO: Implement getOpenOrdersList() method.
    }

    /**
     * @inheritDoc
     */
    public function getOrderTrades(int $exchangeOrderId): array
    {
        // TODO: Implement getOrderTrades() method.
    }

    /**
     * @inheritDoc
     * @throws ApiException
     * @throws Exception
     */
    public function sendPrivateQuery(string $apiName, array $payload, string $method = 'POST', string $apiKey = 'rest')
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

        $res = CurlClient::sendQuery(self::getApiUrl() . $apiName, $payload, $headers, $params);
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
    public static function sendPublicQuery(string $apiName, array $payload)
    {
        $curlOptions = ['CURLOPT_POST' => false];
        $apiData = CurlClient::sendQuery(self::getApiUrl() . $apiName, $payload, [], $curlOptions);
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