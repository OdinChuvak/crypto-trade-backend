<?php

namespace app\exchanges;

use app\models\Pair;

interface ExchangeInterface
{
    /** Получит ключи доступа к API биржи, для переданного юзера */
    public function __construct($userId);

    /** Вернет URL API биржи */
    public static function getApiUrl(string $apiKey): string;

    /** Вернет название объект типа DataMapperInterface */
    public static function getExchangeErrorMap(): array;

    /** Вернет код ошибки из данных, полученных в результате неудачного api-запроса */
    public static function getExchangeErrorCode(array $errorData) : int;

    /** Возвращает список всех валютных пар биржи с актуальными курсами валют */
    public static function getTicker(): array;

    /** Создаст ордер на бирже */
    public function createOrder(Pair $pair, float $quantity, float $price, string $operation): array;

    /** Отменит ордер на бирже */
    public function cancelOrder(int $exchangeOrderId);

    /** Вернет список всех торговых валютных пар биржи */
    public static function getCurrencyPairsList(): array;

    /** Вернет список всех активных ордеров авторизованного пользователя */
    public function getOpenOrdersList(): array;

    /** Вернет список всех продаж в конкретном ордере (ордер может исполняться по частям) */
    public function getOrderTrades(int $exchangeOrderId): array;

    /** Отправит приватный запрос на биржу, получит ответ и обработает его */
    public function sendPrivateQuery(string $apiName, array $payload, string $method, string $apiKey);

    /** Отправит публичный запрос на биржу, получит ответ и обработает его */
    public static function sendPublicQuery(string $apiName, array $payload);
}