<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

class ExchangePair extends BaseModel
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
        return 'exchange_pair';
    }

    public function rules(): array
    {
        return [
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

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'exchange_id' => 'ID биржи',
            'pair_id' => 'ID пары',
            'min_quantity' => 'Минимальный допустимый объем',
            'max_quantity' => 'Максимальный допустимый объем',
            'min_price' => 'Минимальная допустимая цена',
            'max_price' => 'Максимальная допустимая цена',
            'min_amount' => 'Минимальная допустимая сумма покупки',
            'max_amount' => 'Максимальная допустимая сумма покупки',
            'price_precision' => 'Точность цены',
            'commission_taker_percent' => 'Комиссия на покупку',
            'commission_maker_percent' => 'Комиссия на продажу',
            'is_delisted' => 'Произведен делистинг валютной пары',
            'updated_at' => 'Временная метка последнего изменения',
            'created_at' => 'Временная метка создания',
        ];
    }

    public function extraFields(): array
    {
        return [
            'pair',
            'limits',
        ];
    }

    public function getPair(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Pair::class, ['id' => 'pair_id']);
    }

    public function getLimits(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ExchangeRateLimits::class, ['first_currency' => 'first_currency', 'second_currency' => 'second_currency']);
    }
}