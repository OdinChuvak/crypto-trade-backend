<?php

namespace app\mappers;

interface DataMapperInterface
{
    /** Вернет стандартизированные данные */
    public static function mapData($api_name, $data, $payload = []);

    /** Вернет массив из заполненных app\models\CurrencyPair */
    public static function mapCurrencyPairsList(array $exchangeCurrencyPairsList);

    /** Вернет массив из заполненных app\models\Order */
    public static function mapOrdersList($ordersList);

    /** Вернет стандартизированные данные ошибки */
    public static function mapError($data);
}