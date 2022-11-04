<?php

namespace app\exchanges;

use app\models\ExchangePair;

interface ExchangeInterface
{
    /** Получит ключи доступа к API биржи, для переданного юзера */
    public function __construct($user_id);

    /** Вернет URL публичного API биржи */
    public static function getPublicApiUrl(): string;

    /** Вернет URL приватного API биржи */
    public static function getPrivateApiUrl(): string;

    /** Вернет название объект типа DataMapperInterface */
    public static function getExchangeErrorMap(): array;

    /** Вернет код ошибки из данных, полученных в результате неудачного api-запроса */
    public static function getExchangeErrorCode(array $error_data) : int;

    /** Возвращает список всех валютных пар биржи с актуальными курсами валют */
    public static function getTicker(): array;

    /** Создаст ордер на бирже */
    public function createOrder(ExchangePair $pair, float $quantity, float $price, string $operation): array;

    /** Отменит ордер на бирже */
    public function cancelOrder(int $exchange_order_id);

    /** Вернет список всех торговых валютных пар биржи */
    public static function getCurrencyPairsList(): array;

    /** Вернет список всех активных ордеров авторизованного пользователя */
    public function getOpenOrdersList(): array;

    /** Вернет список всех продаж в конкретном ордере (ордер может исполняться по частям) */
    public function getOrderTrades(int $exchange_order_id): array;

    /** Отправит приватный запрос на биржу, получит ответ и обработает его */
    public function sendPrivateQuery(string $api_name, array $payload, string $method);

    /** Отправит публичный запрос на биржу, получит ответ и обработает его */
    public static function sendPublicQuery(string $api_name, array $payload);
}