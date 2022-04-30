<?php

namespace app\helpers;

/**
 * Class FunctionBox
 * @package app\helpers
 *
 * Класс, содержащий самые разные функции, которые не нашли своего места в архитектуре приложения
 */
class FunctionBox
{
    /**
     * Метод возвращает id аутентифицированного пользователя,
     * если таковой существует. В скриптах, где не требуется аутентификация,
     * метод вернет null. Например, в консольных командах, где не подключен
     * компонент user.
     */
    public static function getIdentityId(): int|string|null
    {
        return isset(\Yii::$app->user) ? \Yii::$app->user->getId() : null;
    }
}