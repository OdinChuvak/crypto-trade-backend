<?php

namespace app\models;

use yii\db\ActiveRecord;

class CurrencyPair extends ActiveRecord
{
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
                ],
                'required'
            ]
        ];
    }
}