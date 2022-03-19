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
                    'created_at',
                    'placed_at',
                    'executed_at',
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
        $grid = TradingLine::findOne(['id' => $this->trading_line_id]);
        $pair = CurrencyPair::findOne(['id' => $grid->pair_id]);

        if (!($this->required_trading_rate >= $pair->min_price
            && $this->required_trading_rate <= $pair->max_price)) {
            $errorMsg = 'Invalid value for the currency rate. Acceptable rate values are from '.$pair->min_price.' to '.$pair->max_price.'.';
            $this->addError('required_trading_rate', $errorMsg);
        }
    }

    public function allowedQuantity()
    {
        $grid = TradingLine::findOne(['id' => $this->trading_line_id]);
        $pair = CurrencyPair::findOne(['id' => $grid->pair_id]);
        $actualQuantity = round($grid->order_amount / $this->required_trading_rate, 6);

        if ($actualQuantity < $pair->min_quantity || $actualQuantity > $pair->max_quantity) {
            $errorMsg = 'Incorrect value of the quantity of purchased currency. Valid values are from '.$pair->min_quantity.' to '.$pair->max_quantity.'. Please change the currency rate or amount values to adjust the quantity.';
            $this->addError('required_trading_rate', $errorMsg);
        }
    }

    public function getLine(): \yii\db\ActiveQuery
    {
        return $this->hasOne(TradingLine::class, ['id' => 'trading_line_id']);
    }

    public function getPair($exchange_id): \yii\db\ActiveQuery
    {
        return $this->hasOne(ExchangeCurrencyPair::class, ['pair_id' => 'pair_id'])
            ->onCondition(['exchange_id' => $exchange_id])
            ->via('line');
    }

    public function getPrevious()
    {
        return $this->previous_order_id ? Order::findOne($this->previous_order_id) : null;
    }
}