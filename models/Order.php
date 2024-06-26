<?php

namespace app\models;

use app\helpers\FunctionBox;

class Order extends BaseModel
{
    public static function tableName(): string
    {
        return 'order';
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID ордера',
            'user_id' => 'ID пользователя',
            'exchange_order_id' => 'ID ордера на криптовалютной бирже',
            'trading_line_id' => 'ID сетки ордера',
            'operation' => 'Операция производимая посредством ордера',
            'required_rate' => 'Требуемый курс валюты для исполнения ордера',
            'actual_rate' => 'Реальный курс исполнения ордера',
            'invested' => 'Инвестированная сумма',
            'received' => 'Полученная сумма',
            'commission_amount' => 'Размер комиссии',
            'is_easy_placement' => 'Легкое размещение',
            'is_placed' => 'Размещен на бирже',
            'is_executed' => 'Исполнен',
            'is_continued' => 'Был продолжен',
            'is_error' => 'Возникла ошибка',
            'is_canceled' => 'Отменен',
            'created_at' => 'Время создания ордера в приложении',
            'placed_at' => 'Время размещения ордера',
            'executed_at' => 'Время исполнения ордера',
        ];
    }

    public function rules(): array
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'id',
                    'trading_line_id',
                    'created_at',
                    'placed_at',
                    'executed_at',
                    'exchange_order_id',
                ],
                'safe'
            ],
            [
                [
                    'trading_line_id',
                    'operation',
                    'required_rate',
                    'user_id',
                ],
                'required',
                'message' => 'The value cannot be empty.',
            ],
            ['required_rate', 'allowedExchangeRate'],
            ['required_rate', 'allowedQuantity'],
            [
                [
                    'required_rate',
                    'actual_rate',
                    'invested',
                    'received',
                    'commission_amount',
                ],
                'double',
                'message' => 'The value must be a floating point number.',
            ],
            [
                [
                    'is_easy_placement',
                    'is_error',
                    'is_placed',
                    'is_executed',
                    'is_continued',
                    'is_canceled',
                ],
                'boolean',
                'message' => 'This boolean value.'
            ]
        ];
    }

    public function allowedExchangeRate()
    {
        $line = TradingLine::findOne(['id' => $this->trading_line_id]);
        $pair = ExchangePair::findOne([
            'pair_id' => $line->pair_id,
            'exchange_id' => $line->exchange_id,
        ]);

        if (!($this->required_rate >= $pair->min_price
            && $this->required_rate <= $pair->max_price)) {
            $errorMsg = 'Недопустимое значение цены. Допустимы значения от '.$pair->min_price.' до '.$pair->max_price.'.';
            $this->addError('required_rate', $errorMsg);
        }
    }

    public function allowedQuantity()
    {
        $line = TradingLine::findOne(['id' => $this->trading_line_id]);
        $pair = ExchangePair::findOne([
            'pair_id' => $line->pair_id,
            'exchange_id' => $line->exchange_id,
        ]);
        $actualQuantity = round($line->first_order_amount / $this->required_rate, 6);

        if ($actualQuantity < $pair->min_quantity || $actualQuantity > $pair->max_quantity) {

            $errorMsg = 'Недопустимое значение количества для данной пары. 
            Допустимы значения от '.$pair->min_quantity.' до '.$pair->max_quantity.'. 
            Пожалуйста, измените значение курса валюты или суммы, чтобы скорректировать количество.';

            $this->addError('required_rate', $errorMsg);
        }
    }

    public function getLine(): \yii\db\ActiveQuery
    {
        return $this->hasOne(TradingLine::class, ['id' => 'trading_line_id']);
    }

    public function getPair(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Pair::class, [
            'id' => 'pair_id'
        ])->via('line');
    }

    public function getExchangePair(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ExchangePair::class, [
            'pair_id' => 'pair_id',
            'exchange_id' => 'exchange_id'
        ])->via('line');
    }
}