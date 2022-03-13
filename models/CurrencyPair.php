<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

class CurrencyPair extends BaseModel
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

    public static function tableName()
    {
        return 'currency_pair';
    }

    public function rules()
    {
        return [
            [
                [
                    'name',
                    'first_currency',
                    'second_currency'
                ],
                'string'
            ],
            [
                [
                    'min_quantity',
                    'max_quantity',
                    'min_price',
                    'max_price',
                    'min_amount',
                    'max_amount',
                    'price_precision',
                    'commission_taker_percent',
                    'commission_maker_percent',
                ],
                'required'
            ],
            [
                [
                    'id',
                    'exchange_id',
                    'is_delisted',
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
}