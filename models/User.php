<?php

namespace app\models;

use yii\db\ActiveRecord;

class User extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'user';
    }

    public function rules(): array
    {
        return [
            [
                [
                    'email',
                    'password_hash',
                    'access_token',
                ],
                'string'
            ],
            [
                [
                    'created_at',
                ],
                'datetime',
                'format' => 'php:Y-m-d H:i:s'
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'email' => 'E-mail пользователя',
            'password_hash' => 'Хеш пароля',
            'access_token' => 'Токен доступа',
            'created_at' => 'Временная метка создания',
        ];
    }
}