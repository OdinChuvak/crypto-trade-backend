<?php

namespace app\models;

class ExchangeRateLimits extends BaseModel
{
    public static function tableName(): string
    {
        return 'exchange_rate_limits';
    }

    public function rules(): array
    {
        return [
            [
                [
                    'first_currency',
                    'second_currency'
                ],
                'string'
            ],
            [
                [
                    'upper_limit',
                    'lower_limit',
                ],
                'double',
                'message' => 'The value must be a floating point number.',
            ],
        ];
    }
}