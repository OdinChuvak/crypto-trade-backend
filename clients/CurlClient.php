<?php

namespace app\clients;

use app\helpers\Logger;
use Exception;

class CurlClient implements HttpClientInterface
{
    /**
     * Настройки Curl, заданные по умолчанию
     *
     * @return array
     */
    private static function getDefaultCurlOptions(): array
    {
        return [
            'CURLOPT_USERAGENT' => 'Mozilla/4.0 (compatible; PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')',
            'CURLOPT_POST' => true,
            'CURLOPT_SSL_VERIFYPEER' => false,
        ];
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    public static function sendQuery(string $url, string $method = "GET", array $payload = null, array $headers = null, array $params = null): bool|string
    {
        // Дефолтные параметры CURL
        $defaultOptions = self::getDefaultCurlOptions();

        // Инициализируем CURL
        $curl = curl_init();

        // Устанавливаем метод запроса
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

        // Отдавать результат curl_exec в виде строки, а не выводить сразу
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);

        // Установим агент запроса
        curl_setopt($curl, CURLOPT_USERAGENT,
            $params['CURLOPT_USERAGENT'] ?? $defaultOptions['CURLOPT_USERAGENT']);

        // Установим заголовки запроса
        if ($headers)
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        // Отключим проверку SSL сертификатов адресата
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        // Формируем строку параметров
        $queryParams = $payload ? http_build_query($payload, '', '&') : null;

        // Обработка метода GET
        if ($method === "GET" || $method === "DELETE") {
            $url .= $queryParams ? '?' . $queryParams : '';
        }

        // Обработка метода POST
        if ($method === "POST") {

            if ($queryParams)
                curl_setopt($curl, CURLOPT_POSTFIELDS, $queryParams);

            curl_setopt($curl, CURLOPT_POST, true);
        }

        // Установим URL запроса
        curl_setopt($curl, CURLOPT_URL, $url);

        // Шлем запрос
        $result = curl_exec($curl);

        Logger::curlClient([
            'url' => $url,
            'method' => $method,
            'payload' => $payload,
            'answer' => $result,
        ]);

        if ($result === false) {
            throw new Exception('Не удалось получить ответ: ' . curl_error($curl));
        }

        return $result;
    }
}