<?php

namespace app\models;

use yii\db\ActiveRecord;

abstract class BaseModel extends ActiveRecord
{
    public static function add($data, $formName = '')
    {
        $model = new static();

        return ($model->load($data, $formName) && $model->save());
    }
}