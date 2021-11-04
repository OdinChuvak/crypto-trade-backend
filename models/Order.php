<?php

namespace app\models;

use app\helpers\FunctionBox;
use yii\db\ActiveRecord;

class Order extends ActiveRecord
{
    public static function tableName()
    {
        return 'order';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'trading_grid_id',
                    'operation',
                    'required_trading_rate'
                ],
                'required',
                'message' => 'The value cannot be empty.',
            ],
            [
                [
                    'is_error',
                    'is_placed'
                ],
                'boolean',
                'message' => 'This boolean value.'
            ]
        ];
    }

    public function getGrid()
    {
        return $this->hasOne(TradingGrid::class, ['id' => 'trading_grid_id']);
    }

    public function getPair()
    {
        return $this->hasOne(CurrencyPair::class, ['id' => 'pair_id'])->via('grid');
    }
}