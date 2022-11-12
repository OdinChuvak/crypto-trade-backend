<?php

namespace app\helpers;

class Logger
{
    public static function curlClient(array $params): void
    {
        $EXCEPT_URLS = [
            'https://api.binance.com/api/v3/ticker/price'
        ];

        if (!in_array($params['url'], $EXCEPT_URLS)) {
            \Yii::info("Запрос: {$params['url']}", 'curl-client');
            \Yii::info("Метод: {$params['method']}", 'curl-client');
            \Yii::info("Нагрузка: " . print_r($params['payload'], true), 'curl-client');
            \Yii::info("Ответ: {$params['answer']}\n", 'curl-client');
        }
    }
}