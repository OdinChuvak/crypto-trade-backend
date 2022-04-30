<?php

namespace app\models;

use JetBrains\PhpStorm\ArrayShape;

class Exchange extends BaseModel
{
    public static function tableName(): string
    {
        return 'exchange';
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID биржи',
            'name' => 'Название биржи',
        ];
    }

    public function rules(): array
    {
        return [
            [
                [
                    'id',
                    'name'
                ],
                'safe'
            ],
            [
                [
                    'name',
                ],
                'required',
                'message' => 'Название биржи не может быть пустым',
            ],
        ];
    }
}