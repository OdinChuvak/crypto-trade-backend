<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

class Pair extends BaseModel
{
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public static function tableName(): string
    {
        return 'pair';
    }

    public function rules(): array
    {
        return [
            [
                [
                    'first_currency',
                    'second_currency'
                ],
                'string'
            ],
            [
                [
                    'name',
                ],
                'string'
            ],
            [
                [
                    'id',
                ],
                'safe'
            ],
            [
                [
                    'updated_at',
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
            'name' => 'Идентификатор валютной пары. Например, BTC/USD',
            'first_currency' => 'Идентификатор первой валюты в паре',
            'second_currency' => 'Идентификатор второй валюты в паре',
            'updated_at' => 'Временная метка последнего изменения',
            'created_at' => 'Временная метка создания',
        ];
    }
}