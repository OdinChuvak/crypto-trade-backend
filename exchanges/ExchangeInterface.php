<?php

namespace app\exchanges;

use app\clients\HttpClientInterface;

interface ExchangeInterface
{
    /** Получит ключи доступа к API биржи, для переданного юзера */
    public function __construct($user_id);

    /** Вернет URL API биржи */
    public static function getApiUrl();

    /** Вернет название объект типа DataMapperInterface */
    public static function getDataMapperClass();

    /** Создаст ордер на бирже */
    public function createOrder(array $orderData);

    /** Отменит ордер на бирже */
    public function cancelOrder(int $order_id);

    /** Вернет список всех торговых валютных пар биржи */
    public static function getCurrencyPairsList();

    /** Отправит приватный запрос на биржу, получит ответ и обработает его */
    public function sendPrivateQuery(string $api_name, array $payload);

    /** Отправит публичный запрос на биржу, получит ответ и обработает его */
    public static function sendPublicQuery(string $api_name, array $payload);
}