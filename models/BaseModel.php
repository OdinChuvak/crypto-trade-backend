<?php

namespace app\models;

use yii\db\ActiveRecord;

abstract class BaseModel extends ActiveRecord
{
    /**
     * Метод пытается добавить запись в таблице БД
     * В случае успеха вернет записанную модель,
     * а в случае ошибки, ту же модель, но содержащую
     * данные ошибки
     *
     * @param $data
     * @param string $formName
     * @return static
     */
    public static function add($data, $formName = '')
    {
        $model = new static();

        $model->load($data, $formName);
        $model->save();

        return $model;
    }
}