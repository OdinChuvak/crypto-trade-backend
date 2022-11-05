<?php

namespace app\clients;

interface HttpClientInterface
{
    /** Пошлет http-запрос */
    public static function sendQuery(
        string $url,
        string $method,
        array $payload,
        array $headers,
        array $params
    );
}