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
            [['name', 'first_currency', 'second_currency'], 'string']
        ];
    }
}