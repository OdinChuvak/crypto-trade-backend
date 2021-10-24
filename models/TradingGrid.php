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
            [['pair_id', 'order_step', 'order_amount'], 'required', 'message' => 'The value cannot be empty.'],
        ];
    }
}