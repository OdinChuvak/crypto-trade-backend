<?php

namespace app\clients;

interface HttpClientInterface
{
    /** Пошлет http-запрос */
    public static function sendQuery(string $path, array $payload = [], array $headers = [], array $params = []);
}