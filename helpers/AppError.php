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
     * Ошибка аутентификации
     */
    const AUTH_ERROR = [
        'code' => 1001,
        'type' => 'error',
        'message' => 'Authentication error'
    ];

    /**
     * Недостаточно средст
     */
    const INSUFFICIENT_FUNDS = [
        'code' => 2001,
        'type' => 'error',
        'message' => 'There are not enough funds on the account to complete the operation'
    ];
}