<?php

namespace app\models;

use app\helpers\AppError;
use app\helpers\FunctionBox;
use yii\db\ActiveRecord;

class Order extends ActiveRecord
{
    public static function tableName()
    {
        return 'order';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'trading_grid_id',
                    'operation',
                    'required_trading_rate',
                    'user_id',
                ],
                'required',
                'message' => 'The value cannot be empty.',
            ],
            ['required_trading_rate', 'admissibleExchangeRate'],
            [
                [
                    'is_error',
                    'is_placed'
                ],
                'boolean',
                'message' => 'This boolean value.'
            ]
        ];
    }

    public function admissibleExchangeRate()
    {
        $grid = TradingGrid::findOne(['id' => $this->trading_grid_id]);
        $pair = CurrencyPair::findOne(['id' => $grid->pair_id]);

        if (!($this->required_trading_rate >= $pair->min_price
            && $this->required_trading_rate <= $pair->max_price)) {
            $errorMsg = 'Invalid value for the currency rate. Acceptable rate values are from '.$pair->min_price.' to '.$pair->max_price.'.';
            $this->addError('required_trading_rate', $errorMsg);
        }
    }

    public static function getQuantity($order_id)
    {
        $order = self::find()
            ->with('grid')
            ->where(['id' => $order_id])
            ->one();

        $previousOrder = self::find()
            ->with('grid')
            ->where(['id' => $order->previous_order_id])
            ->one();

        $grid = $order->grid;

        if (empty($previousOrder)) {
            return round($grid->order_amount / $order->required_trading_rate, 7);
        }

        if ($order->operation === $previousOrder->operation) {
            return null;
        }

        if ($grid->trading_method === 1) {
            return $order->operation === 'buy'
                ? round($previousOrder->received / $order->required_trading_rate, 7)
                : $previousOrder->received;
        }
        elseif ($grid->trading_method === 2) {
            return $order->operation === 'buy'
                ? round($grid->order_amount / $order->required_trading_rate, 7)
                : $previousOrder->received;
        }
        elseif ($grid->trading_method === 3) {
            return $order->operation === 'buy'
                ? round($previousOrder->received / $order->required_trading_rate, 7)
                : round($grid->order_amount / $previousOrder->required_trading_rate, 7);
        }
        else {
            return null;
        }
    }

    public function getGrid()
    {
        return $this->hasOne(TradingGrid::class, ['id' => 'trading_grid_id']);
    }

    public function getPair()
    {
        return $this->hasOne(CurrencyPair::class, ['id' => 'pair_id'])->via('grid');
    }
}