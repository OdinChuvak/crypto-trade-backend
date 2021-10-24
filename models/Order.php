<?php

namespace app\models;

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
            [['trading_grid_id', 'operation', 'required_trading_rate'], 'required', 'message' => 'The value cannot be empty.'],
        ];
    }
}