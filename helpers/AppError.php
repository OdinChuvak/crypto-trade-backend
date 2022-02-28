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
        'message' => 'Нет файла с ключами аутентификации'
    ];

    /**
     * Неверный ключ API
     */
    const WRONG_API_KEY = [
        'code' => 1001,
        'type' => 'error',
        'message' => 'Неправильный API-ключ'
    ];

    /**
     * Неверная подпись
     */
    const INCORRECT_SIGNATURE = [
        'code' => 1002,
        'type' => 'error',
        'message' => 'Неправильная подпись'
    ];

    /**
     * Ошибка аутентификации
     */
    const KEY_IS_NOT_ACTIVATED = [
        'code' => 1003,
        'type' => 'error',
        'message' => 'Доступ запрещен, ключ API не активирован'
    ];

    /**
     * Ошибка параметра
     */
    const PARAMETER_ERROR = [
        'code' => 1004,
        'type' => 'error',
        'message' => 'Ошибка параметра'
    ];

    /**
     * Недостаточно средст
     */
    const INSUFFICIENT_FUNDS = [
        'code' => 2001,
        'type' => 'error',
        'message' => 'На счете недостаточно средств для совершения операции'
    ];

    /**
     * Количество закупаемой валюты меньше допустимого минимума
     */
    const QUANTITY_LESS = [
        'code' => 2002,
        'type' => 'error',
        'message' => 'Количество в ордере меньше допустимого минимума для этой пары'
    ];

    /**
     * Количество закупаемой валюты меньше допустимого минимума
     */
    const QUANTITY_MORE = [
        'code' => 2003,
        'type' => 'error',
        'message' => 'Количество в ордере больше максимально допустимого для данной пары'
    ];

    /**
     * Ордер не найден
     */
    const ORDER_NOT_FOUND = [
        'code' => 2004,
        'type' => 'error',
        'message' => 'Ордер не найден'
    ];

    /**
     * Проблема создания ордера на покупку
     */
    const BUY_ORDER_CREATION_PROBLEM = [
        'code' => 2005,
        'type' => 'error',
        'message' => 'Не удалось создать ордер на покупку'
    ];

    /**
     * Проблема создания ордера на продажу
     */
    const SELL_ORDER_CREATION_PROBLEM = [
        'code' => 2006,
        'type' => 'error',
        'message' => 'Не удалось создать ордер на продажу'
    ];

    /**
     * Неизвестная ошибка
     */
    const UNKNOWN_ERROR = [
        'code' => 9999,
        'type' => 'error',
        'message' => 'Неизвестная ошибка'
    ];

    public static function errorMap(): array
    {
        return [
            '40005' => self::INCORRECT_SIGNATURE,
            '40017' => self::WRONG_API_KEY,
            '40030' => self::KEY_IS_NOT_ACTIVATED,
            '50018' => self::PARAMETER_ERROR,
            '50052' => self::INSUFFICIENT_FUNDS,
            '50054' => self::INSUFFICIENT_FUNDS,
            '50277' => self::QUANTITY_LESS,
            '50304' => self::ORDER_NOT_FOUND,
        ];
    }

    public static function getMappingError($errorCode): array
    {
        return self::errorMap()[$errorCode] ?? self::UNKNOWN_ERROR;
    }

    public static function getExchangeErrorFromMessage($errorMessage)
    {
        preg_match('/\d{5}/', $errorMessage, $exchange_error_code);

        return $exchange_error_code[0];
    }
}