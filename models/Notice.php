<?php

namespace app\models;

use app\helpers\FunctionBox;

class Notice extends BaseNotice
{
    public static function tableName(): string
    {
        return 'notice';
    }

    public function rules(): array
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'reference',
                    'reference_id',
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