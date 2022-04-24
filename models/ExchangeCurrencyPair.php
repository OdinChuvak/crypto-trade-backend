<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

class ExchangeCurrencyPair extends BaseModel
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
        return 'exchange_currency_pair';
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
                    'pair_id',
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

    public function getPair(): \yii\db\ActiveQuery
    {
        return $this->hasOne(CurrencyPair::class, ['id' => 'pair_id']);
    }

    public function getLimits(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ExchangeRateLimits::class, ['first_currency' => 'first_currency', 'second_currency' => 'second_currency']);
    }
}