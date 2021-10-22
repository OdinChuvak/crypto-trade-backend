<?php

namespace app\models;

use \yii\db\ActiveRecord;

class TradingGrid extends ActiveRecord
{
    public static function tableName()
    {
        return 'trading_grid';
    }
}