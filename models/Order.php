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
            'previous_order_id' => 'ID предыдущего ордера',
            'continued_order_id' => 'ID продолженного ордера',
            'operation' => 'Операция производимая посредством ордера',
            'required_trading_rate' => 'Требуемый курс валюты для исполнения ордера',
            'actual_trading_rate' => 'Реальный курс исполнения ордера',
            'invested' => 'Инвестированная сумма',
            'received' => 'Полученная сумма',
            'commission_amount' => 'Размер комиссии',
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

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'id',
                    'trading_line_id',
                    'previous_order_id',
                    'continued_order_id',
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
                    'required_trading_rate',
                    'user_id',
                ],
                'required',
                'message' => 'The value cannot be empty.',
            ],
            ['required_trading_rate', 'allowedExchangeRate'],
            ['required_trading_rate', 'allowedQuantity'],
            [
                [
                    'required_trading_rate',
                    'actual_trading_rate',
                    'invested',
                    'received',
                    'commission_amount',
                ],
                'double',
                'message' => 'The value must be a floating point number.',
            ],
            [
                [
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
        $pair = ExchangeCurrencyPair::findOne([
            'pair_id' => $line->pair_id,
            'exchange_id' => $line->exchange_id,
        ]);

        if (!($this->required_trading_rate >= $pair->min_price
            && $this->required_trading_rate <= $pair->max_price)) {
            $errorMsg = 'Недопустимое значение цены. Допустимы значения от '.$pair->min_price.' до '.$pair->max_price.'.';
            $this->addError('required_trading_rate', $errorMsg);
        }
    }

    public function allowedQuantity()
    {
        $line = TradingLine::findOne(['id' => $this->trading_line_id]);
        $pair = ExchangeCurrencyPair::findOne([
            'pair_id' => $line->pair_id,
            'exchange_id' => $line->exchange_id,
        ]);
        $actualQuantity = round($line->amount / $this->required_trading_rate, 6);

        if ($actualQuantity < $pair->min_quantity || $actualQuantity > $pair->max_quantity) {

            $errorMsg = 'Недопустимое значение количества для данной пары. 
            Допустимы значения от '.$pair->min_quantity.' до '.$pair->max_quantity.'. 
            Пожалуйста, измените значение курса валюты или суммы, чтобы скорректировать количество.';

            $this->addError('required_trading_rate', $errorMsg);
        }
    }

    public function getLine(): \yii\db\ActiveQuery
    {
        return $this->hasOne(TradingLine::class, ['id' => 'trading_line_id']);
    }

    public function getPair(): \yii\db\ActiveQuery
    {
        return $this->hasOne(CurrencyPair::class, ['id' => 'pair_id'])->via('line');
    }

    public function getPrevious(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'previous_order_id']);
    }

    public function getContinued(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Order::class, ['id' => 'continued_order_id']);
    }
}