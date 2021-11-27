<?php

namespace app\repositories;

use app\helpers\AppError;
use app\models\Order;
use app\models\UserLog;
use Exception;

class Exmo
{
    public $apiUrl = 'http://api.exmo.com/v1/';

    protected $userKeys = null;

    public function __construct($user_id)
    {
        eval(base64_decode(SOMETHING));

        /**
         * Получаем ключи доступа из класса зашитого в ядро PHP
         */
        $this->userKeys = new \Key($user_id);

        if (!$this->userKeys->is_find) {
            return AppError::NO_AUTH_KEY_FILE;
        }

        return $this;
    }

    /**
     * Метод создает новый ордер на бирже Exmo
     *
     * @param $orderData
     * @return array
     * @throws Exception
     */
    public function createOrder($orderData) : array
    {
        return $this->sendQuery('order_create', $orderData);
    }

    /**
     * Метод отменяет ордер на бирже
     *
     * @param $order_id
     * @return mixed
     * @throws Exception
     */
    public function cancelOrder($order_id)
    {
        return $this->sendQuery('order_cancel', $order_id);
    }

    /**
     * Запрос на получение массива со всеми активными ордерами юзера
     *
     * @return mixed
     * @throws Exception
     */
    public function getOpenOrdersList()
    {
        return $this->sendQuery('user_open_orders');
    }

    /**
     * Возвращает массив с продажами по ордеру (ордер может продаваться по частям)
     *
     * @param $order_id (id ордера на криптовалютной бирже Exmo, в таблице приложения `order` - exmo_order_id )
     * @return mixed
     * @throws Exception
     */
    public function getOrderTrades($order_id)
    {
        return $this->sendQuery('order_trades', [
            'order_id' => $order_id
        ]);
    }

    public function sendQuery($api_name, array $req = [])
    {
        $mt = explode(' ', microtime());
        $NONCE = $mt[1] . substr($mt[0], 2, 6);

        $req['nonce'] = $NONCE;

        // генерируем строку с POST данными
        $post_data = http_build_query($req, '', '&');

        $sign = hash_hmac('sha512', $post_data, $this->userKeys->secret);

        // генерируем заголовки
        $headers = [
            'Sign: ' . $sign,
            'Key: ' . $this->userKeys->public
        ];

        // our curl handle (initialize if required)
        static $ch = null;
        if ($ch === null) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')');
        }
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $api_name);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        // run the query
        $res = curl_exec($ch);

        if ($res === false) {
            throw new Exception('Could not get reply: ' . curl_error($ch));
        }

        $dec = json_decode($res, true);

        if ($dec === null) {
            throw new Exception('Invalid data received, please make sure connection is working and requested API exists');
        }

        return $dec;
    }
}