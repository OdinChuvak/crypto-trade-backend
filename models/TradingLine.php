<?php

namespace app\models;

use app\helpers\FunctionBox;
use \yii\db\ActiveRecord;

class TradingLine extends ActiveRecord
{
    public static function tableName()
    {
        return 'trading_line';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'pair_id',
                    'exchange_id',
                    'step_up',
                    'step_down',
                    'amount'
                ],
                'required',
                'message' => 'The value cannot be empty.'
            ],
            ['amount', 'allowedAmount'],
            ['is_stopped', 'boolean', 'message' => 'This boolean value.']
        ];
    }

    public function extraFields(): array
    {
        return [
            'pair'
        ];
    }

    public function allowedAmount()
    {
        $pair = CurrencyPair::findOne(['id' => $this->pair_id]);

        if (!($this->amount >= $pair->min_amount
            && $this->amount <= $pair->max_amount)) {
            $errorMsg = 'Invalid value for the amount field for the currency pair. The value must be between '.$pair->min_amount.' and '.$pair->max_amount.'.';
            $this->addError('amount', $errorMsg);
        }
    }

    public function getPair()
    {
        return $this->hasOne(CurrencyPair::class, ['id' => 'pair_id']);
    }
}