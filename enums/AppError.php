<?php

namespace app\enums;

/**
 * Class AppError - Класс внутренних ошибок сервиса
 *
 * @package app\enums
 */
class AppError
{
    /**
     * Отсутствует файл с ключами аутентификации на бирже
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
     * Не указан требуемый для авторизации http-заголовок
     */
    const HEADER_KEY_IS_NOT_FIND = [
        'code' => 1005,
        'type' => 'error',
        'message' => 'Не указан требуемый для авторизации http-заголовок'
    ];

    /**
     * Превышена скорость запросов
     */
    const REQUESTS_LIMIT_IS_EXCEEDED = [
        'code' => 1006,
        'type' => 'error',
        'message' => 'Превышен лимит запросов'
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
     * Цена покупки меньше допустимого минимума
     */
    const PRICE_LESS = [
        'code' => 2004,
        'type' => 'error',
        'message' => 'Цена меньше допустимого минимума для этой пары'
    ];

    /**
     * Цена покупки больше допустимого минимума
     */
    const PRICE_MORE = [
        'code' => 2005,
        'type' => 'error',
        'message' => 'Цена больше допустимого максимума для данной пары'
    ];

    /**
     * Сумма покупки меньше допустимого минимума
     */
    const AMOUNT_LESS = [
        'code' => 2006,
        'type' => 'error',
        'message' => 'Сумма покупки меньше допустимого минимума для этой пары'
    ];

    /**
     * Сумма покупки больше допустимого минимума
     */
    const AMOUNT_MORE = [
        'code' => 2007,
        'type' => 'error',
        'message' => 'Сумма покупки больше максимально допустимого для данной пары'
    ];

    /**
     * Ордер не найден
     */
    const ORDER_NOT_FOUND = [
        'code' => 2008,
        'type' => 'error',
        'message' => 'Ордер не найден'
    ];

    /**
     * Проблема создания ордера на бирже по неопределенным причинам
     */
    const ORDER_CREATION_PROBLEM = [
        'code' => 2009,
        'type' => 'error',
        'message' => 'Не удалось создать ордер на бирже по неопределенным причинам'
    ];

    /**
     * Неизвестная ошибка
     */
    const UNKNOWN_ERROR = [
        'code' => 9999,
        'type' => 'error',
        'message' => 'Неизвестная ошибка'
    ];
}