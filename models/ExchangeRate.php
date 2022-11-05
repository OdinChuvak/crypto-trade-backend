<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

class ExchangeRate extends BaseModel
{
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => false,
                'updatedAtAttribute' => 'updated_at',
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public static function tableName(): string
    {
        return 'exchange_rate';
    }

    public function rules(): array
    {
        return [
            [
                [
                    'id',
                    'exchange_id',
                    'pair_id',
                ],
                'safe'
            ],
            [
                [
                    'rate_dynamic'
                ],
                'integer',
                'min' => -1,
                'max' => 1
            ],
            [
                [
                    'value',
                ],
                'double',
                'message' => 'The value must be a floating point number.',
            ],
        ];
    }
}