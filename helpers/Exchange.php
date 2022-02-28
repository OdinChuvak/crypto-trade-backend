<?php

namespace app\helpers;

use app\exchanges\Exmo;
use Exception;

class Exchange
{
    public static array $exchanges = [
        1 => Exmo::class
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

        return new self::$exchanges[$exchange_id]($user_id);
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