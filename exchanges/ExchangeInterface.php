<?php

namespace app\exchanges;

use app\dto\ExchangeOrder;
use app\models\ExchangeCurrencyPair;

interface ExchangeInterface
{
    /** Получит ключи доступа к API биржи, для переданного юзера */
    public function __construct($user_id);

    /** Вернет URL API биржи */
    public static function getApiUrl(): string;

    /** Вернет название объект типа DataMapperInterface */
    public static function getExchangeErrorMap(): array;

    /** Вернет код ошибки из данных, полученных в результате неудачного api-запроса */
    public static function getExchangeErrorCode(string $error_message) : int;

    /** Создаст ордер на бирже */
    public function createOrder(ExchangeCurrencyPair $pair, float $quantity, float $price, string $operation): array;

    /** Отменит ордер на бирже */
    public function cancelOrder(int $order_id);

    /** Вернет список всех торговых валютных пар биржи */
    public static function getCurrencyPairsList(): array;

    /** Отправит приватный запрос на биржу, получит ответ и обработает его */
    public function sendPrivateQuery(string $api_name, array $payload);

    /** Отправит публичный запрос на биржу, получит ответ и обработает его */
    public static function sendPublicQuery(string $api_name, array $payload);
}