<?php

namespace app\exchanges;

use app\clients\CurlClient;
use app\helpers\AppError;
use app\exceptions\ApiException;
use app\models\ExchangeCurrencyPair;
use Exception;

class Exmo extends BaseExchange implements ExchangeInterface
{
    /**
     * @inheritDoc
     */
    public static function getApiUrl(): string
    {
        return 'https://api.exmo.com/v1.1/';
    }

    /**
     * @return array
     */
    public static function getExchangeErrorMap(): array
    {
        return [
            '40003' => AppError::HEADER_KEY_IS_NOT_FIND,
            '40005' => AppError::INCORRECT_SIGNATURE,
            '40017' => AppError::WRONG_API_KEY,
            '40030' => AppError::KEY_IS_NOT_ACTIVATED,
            '40034' => AppError::RATE_LIMIT_IS_EXCEEDED,
            '50018' => AppError::PARAMETER_ERROR,
            '50052' => AppError::INSUFFICIENT_FUNDS,
            '50054' => AppError::INSUFFICIENT_FUNDS,
            '50277' => AppError::QUANTITY_LESS,
            '50304' => AppError::ORDER_NOT_FOUND,
        ];
    }

    /**
     * @param mixed $errorApiData
     * @return int
     */
    public static function getExchangeErrorCode(string $error_message): int
    {
        preg_match('/\d{5}/', $error_message, $exchange_error_code);

        return $exchange_error_code[0];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function createOrder(ExchangeCurrencyPair $pair, float $quantity, float $price, string $operation): array
    {
        $apiResult = $this->sendPrivateQuery('order_create', [
            'pair' => $pair->first_currency . '_' . $pair->second_currency,
            'quantity' => $quantity,
            'price' => $price,
            'type' => $operation,
        ]);

        return [
            'exchange_order_id' => $apiResult['order_id']
        ];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function cancelOrder(int $exchange_order_id)
    {
        return $this->sendPrivateQuery('order_cancel', [
            'order_id' => $exchange_order_id
        ]);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getOpenOrdersList(): array
    {
        $apiResult = $this->sendPrivateQuery('user_open_orders');
        $userOpenOrders = [];

        foreach ($apiResult as $pair => $ordersToPair) {
            $pair = explode('_', $pair);

            foreach ($ordersToPair as $order) {
                $userOpenOrders[] = [
                    "first_currency" => $pair[0],
                    "second_currency" => $pair[1],
                    "exchange_order_id" => $order['order_id'],
                    "type" => $order['type'],
                    "price" => $order['price'],
                    "quantity" => $order['quantity'],
                    "amount" => $order['amount'],
                    "created_at" => $order['created'],
                ];
            }
        }

        return $userOpenOrders;
    }

    /**
     * Возвращает массив с продажами по ордеру (ордер может продаваться по частям)
     *
     * @param $exchange_order_id (id ордера на криптовалютной бирже)
     * @return array
     * @throws Exception
     */
    public function getOrderTrades($exchange_order_id): array
    {
        $apiResult = $this->sendPrivateQuery('order_trades', [
            'order_id' => $exchange_order_id
        ]);
        $orderTrades = [];

        foreach ($apiResult["trades"] as $pair => $trade) {
            $orderTrades[] = [
                "order_id" => $trade["order_id"],
                "date" => $trade["date"],
                "type" => $trade["type"],
                "quantity" => $trade["quantity"],
                "price" => $trade["price"],
                "amount" => $trade["amount"],
                "commission_amount" => $trade["commission_amount"],
                "commission_currency" => $trade["commission_currency"],
                "commission_percent" => $trade["commission_percent"]
            ];
        }

        return $orderTrades;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public static function getCurrencyPairsList(): array
    {
        $exchangeCurrencyPairsList = self::sendPublicQuery('pair_settings', []);
        $currencyPairsList = [];

        foreach ($exchangeCurrencyPairsList as $pair => $exchangeCurrencyPair) {
            $pair = explode('_', $pair);

            $currencyPairsList[] = [
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
            ];
        }

        return $currencyPairsList;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function sendPrivateQuery(string $api_name, array $payload = [])
    {
        $mt = explode(' ', microtime());
        $nonce = $mt[1] . substr($mt[0], 2, 6);
        $payload['nonce'] = $nonce;

        // генерируем строку с POST данными
        $post_data = http_build_query($payload, '', '&');
        $sign = hash_hmac('sha512', $post_data, $this->userKeys->secret);

        // генерируем заголовки
        $headers = [
            'Content-length: ' . strlen($post_data),
            'Sign: ' . $sign,
            'Key: ' . $this->userKeys->public,
        ];

        // шлем запрос
        $res = CurlClient::sendQuery(self::getApiUrl() . $api_name, $payload, $headers);
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
    public static function sendPublicQuery(string $api_name, array $payload)
    {
        $curlOptions = ['CURLOPT_POST' => false];
        $apiData = CurlClient::sendQuery(self::getApiUrl() . $api_name, $payload, [], $curlOptions);
        $apiData = json_decode($apiData, true);

        return self::getResponse($apiData);
    }

    /**
     * @throws ApiException
     */
    public static function getResponse(mixed $apiData)
    {
        // если запрос завершился неудачей, выбросим исключение
        if (isset($apiData['result']) && !$apiData['result']) {
            $error = self::getExchangeErrorMap()[self::getExchangeErrorCode($apiData['error'])];
            throw new ApiException($error['message'], $error['code']);
        }

        // иначе вернем ответ запроса
        return $apiData;
    }
}