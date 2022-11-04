<?php

namespace app\models;

class ExchangeRate extends BaseModel
{
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