<?php

namespace app\models;

use app\helpers\FunctionBox;

class UserLog extends BaseModel
{
    public static function tableName()
    {
        return 'user_log';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
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