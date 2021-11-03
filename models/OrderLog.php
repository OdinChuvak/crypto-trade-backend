<?php

namespace app\models;

use yii\db\ActiveRecord;

class OrderLog extends ActiveRecord
{
    public static function tableName()
    {
        return 'order_log';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => \Yii::$app->user->getId()],
            [
                [
                    'order_id',
                    'type',
                    'message'
                ],
                'required',
                'message' => 'The value cannot be empty.',
            ],
        ];
    }
}