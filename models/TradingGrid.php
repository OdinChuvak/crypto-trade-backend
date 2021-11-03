<?php

namespace app\models;

use \yii\db\ActiveRecord;

class TradingGrid extends ActiveRecord
{
    public static function tableName()
    {
        return 'trading_grid';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => \Yii::$app->user->getId()],
            [
                [
                    'pair_id',
                    'order_step',
                    'order_amount'
                ],
                'required',
                'message' => 'The value cannot be empty.'
            ],
        ];
    }

    public function extraFields()
    {
        return [
            'pair'
        ];
    }

    public function getPair()
    {
        return $this->hasOne(CurrencyPair::class, ['id' => 'pair_id']);
    }
}