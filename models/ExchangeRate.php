<?php

namespace app\models;

use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

class ExchangeRate extends BaseModel
{
    /**
     * Срок годности курса валют
     */
    const ACTUAL_RATE_TIME = 300;

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => false,
                'updatedAtAttribute' => 'created_at',
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
                    'value',
                ],
                'double',
                'message' => 'The value must be a floating point number.',
            ],
        ];
    }
}