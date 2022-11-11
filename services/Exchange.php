<?php

namespace app\services;

use app\exchanges\Binance;
use app\exchanges\Exmo;
use Exception;

class Exchange
{
    public static array $exchanges = [
        1 => Exmo::class,
        2 => Binance::class,
    ];

    /**
     * @throws Exception
     */
    public static function getObject($exchange_id, $user_id)
    {
        if (!isset(self::$exchanges[$exchange_id])) {
            throw new Exception('Не найден класс биржи');
        }

        if (empty($user_id)) {
            throw new Exception('Не задан идентификатор пользователя');
        }

        try {
            $exchange = new self::$exchanges[$exchange_id]($user_id);
        } catch (Exception $e) {
            $exchange = false;
        }

        return $exchange;
    }

    /**
     * @throws Exception
     */
    public static function getClass($exchange_id)
    {
        if (!isset(self::$exchanges[$exchange_id])) {
            throw new Exception('Не найден класс биржи');
        }

        return self::$exchanges[$exchange_id];
    }
}