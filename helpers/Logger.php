<?php

namespace app\helpers;

class Logger
{
    public static function curlClient(array $params): void
    {
        $EXCEPT_URLS = [
            'https://api.binance.com/api/v3/ticker/price',
            'https://api.binance.com/sapi/v1/asset/tradeFee',
        ];

        $url = explode('?', $params['url']);
        $url = rtrim($url[0], '/');

        if (!in_array($url, $EXCEPT_URLS)) {
            \Yii::info("Запрос: {$params['url']}", 'curl-client');
            \Yii::info("Метод: {$params['method']}", 'curl-client');
            \Yii::info("Нагрузка: " . print_r($params['payload'], true), 'curl-client');
            \Yii::info("Ответ: {$params['answer']}\n", 'curl-client');
        }
    }
}