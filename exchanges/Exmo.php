<?php

namespace app\exchanges;

use app\clients\CurlClient;
use Exception;

class Exmo extends BaseExchange implements ExchangeInterface
{
    /**
     * @inheritDoc
     */
    public static function getApiUrl(): string
    {
        return 'https://api.exmo.com/v1/';
    }

    public static function getDataMapperClass(): string
    {
        return \app\mappers\Exmo::class;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function createOrder(array $orderData)
    {
        return $this->sendPrivateQuery('order_create', $orderData);
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
     * @return mixed
     * @throws Exception
     */
    public function getOpenOrdersList()
    {
        return $this->sendPrivateQuery('user_open_orders');
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public static function getCurrencyPairsList()
    {
        return self::sendPublicQuery('pair_settings', []);
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

        return (self::getDataMapperClass())::mapData($api_name, $dec, $payload);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public static function sendPublicQuery(string $api_name, array $payload)
    {
        $curlOptions = ['CURLOPT_POST' => false];
        $apiData = CurlClient::sendQuery(self::getApiUrl() . $api_name, $payload, [], $curlOptions);

        return (self::getDataMapperClass())::mapData($api_name, $apiData, $payload);
    }
}