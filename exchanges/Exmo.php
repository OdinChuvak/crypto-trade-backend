<?php

namespace app\exchanges;

use app\clients\CurlClient;
use app\helpers\AppError;
use app\models\CurrencyPair;
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
    public function cancelOrder(int $order_id)
    {
        return $this->sendPrivateQuery('order_cancel', [
            'order_id' => $order_id
        ]);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getOpenOrdersList(): array
    {
        $apiResult = json_decode($this->sendPrivateQuery('user_open_orders'), true);
        $userOpenOrders = [];

        foreach ($apiResult as $pair => $item) {
            $pair = explode('_', $pair);

            $userOpenOrders[] = [
                "first_currency" => $pair[0],
                "second_currency" => $pair[1],
                "exchange_order_id" => $item['order_id'],
                "type" => $item['type'],
                "price" => $item['price'],
                "quantity" => $item['quantity'],
                "amount" => $item['amount'],
                "created_at" => $item['created'],
            ];
        }

        return $userOpenOrders;
    }

    /**
     * Возвращает массив с продажами по ордеру (ордер может продаваться по частям)
     *
     * @param $order_id (id ордера на криптовалютной бирже Exmo, в таблице приложения `order` - exmo_order_id )
     * @return mixed
     * @throws Exception
     */
    public function getOrderTrades($order_id): mixed
    {
        $apiResult = json_decode($this->sendPrivateQuery('order_trades'), true);
        $orderTrades = [];

        foreach ($apiResult["trades"] as $pair => $item) {
            $orderTrades[] = [
                "date" => $item["date"],
                "type" => $item["type"],
                "order_id" => $item["order_id"],
                "quantity" => $item["quantity"],
                "price" => $item["price"],
                "amount" => $item["amount"],
                "commission_amount" => $item["commission_amount"],
                "commission_currency" => $item["commission_currency"],
                "commission_percent" => $item["commission_percent"]
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
        $exchangeCurrencyPairsJson = self::sendPublicQuery('pair_settings', []);
        $exchangeCurrencyPairsList = json_decode($exchangeCurrencyPairsJson, true);
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