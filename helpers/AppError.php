<?php

namespace app\helpers;

/**
 * Class AppError
 * @package app\helpers
 * Класс внутренних ошибок сервиса
 * Реализован, для избежания хард привязки кодов ошибок и сообщений
 */
class AppError
{
    /**
     * Отсутствует файл с ключами аутентификации на бирже Exmo
     */
    const NO_AUTH_KEY_FILE = [
        'code' => 1000,
        'type' => 'error',
        'message' => 'No authentication key file'
    ];

    /**
     * Неверный ключ API
     */
    const WRONG_API_KEY = [
        'code' => 1001,
        'type' => 'error',
        'message' => 'Wrong api key'
    ];

    /**
     * Неверная подпись
     */
    const INCORRECT_SIGNATURE = [
        'code' => 1002,
        'type' => 'error',
        'message' => 'Incorrect signature'
    ];

    /**
     * Ошибка аутентификации
     */
    const KEY_IS_NOT_ACTIVATED = [
        'code' => 1003,
        'type' => 'error',
        'message' => 'Access is denied, API key is not activated'
    ];

    /**
     * Ошибка параметра
     */
    const PARAMETER_ERROR = [
        'code' => 1004,
        'type' => 'error',
        'message' => 'Parameter error'
    ];

    /**
     * Недостаточно средст
     */
    const INSUFFICIENT_FUNDS = [
        'code' => 2001,
        'type' => 'error',
        'message' => 'There are not enough funds on the account to complete the operation'
    ];

    /**
     * Количество закупаемой валюты меньше допустимого минимума
     */
    const QUANTITY_LESS = [
        'code' => 2002,
        'type' => 'error',
        'message' => 'Quantity by order is less than permissible minimum for this pair'
    ];

    /**
     * Ордер не найден
     */
    const ORDER_NOT_FOUND = [
        'code' => 2004,
        'type' => 'error',
        'message' => 'Order was not found'
    ];

    /**
     * Неизвестная ошибка
     */
    const UNKNOWN_ERROR = [
        'code' => 9999,
        'type' => 'error',
        'message' => 'Unknown error'
    ];

    public static function errorMap()
    {
        return [
            '40005' => self::INCORRECT_SIGNATURE,
            '40017' => self::WRONG_API_KEY,
            '40030' => self::KEY_IS_NOT_ACTIVATED,
            '50018' => self::PARAMETER_ERROR,
            '50052' => self::INSUFFICIENT_FUNDS,
            '50277' => self::QUANTITY_LESS,
            '50304' => self::ORDER_NOT_FOUND,
        ];
    }

    public static function getMappingError($errorCode)
    {
        return self::errorMap()[$errorCode] ?: self::UNKNOWN_ERROR;
    }

    public static function getExmoErrorFromMessage($errorMessage)
    {
        preg_match('/\d{5}/', $errorMessage, $exmo_error_code);

        return $exmo_error_code[0];
    }
}