<?php

namespace app\models;

use app\helpers\FunctionBox;

class OrderLog extends BaseModel
{
    public static function tableName()
    {
        return 'order_log';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'trading_grid_id',
                    'order_id',
                    'type',
                    'message'
                ],
                'required',
                'message' => 'The value cannot be empty.',
            ],
            ['error_code', 'integer'],
        ];
    }
}