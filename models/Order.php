<?php

namespace app\models;

use app\helpers\AppError;
use app\helpers\FunctionBox;

class Order extends BaseModel
{
    public static function tableName()
    {
        return 'order';
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID ордера',
            'user_id' => 'ID пользователя',
            'exmo_order_id' => 'ID ордера на криптовалютной бирже EXMO',
            'trading_grid_id' => 'ID сетки ордера',
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
                    'exmo_order_id',
                    'previous_order_id',
                    'created_at',
                    'placed_at',
                    'executed_at',
                ],
                'safe'
            ],
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
        $grid = TradingGrid::findOne(['id' => $this->trading_grid_id]);
        $pair = CurrencyPair::findOne(['id' => $grid->pair_id]);

        if (!($this->required_trading_rate >= $pair->min_price
            && $this->required_trading_rate <= $pair->max_price)) {
            $errorMsg = 'Invalid value for the currency rate. Acceptable rate values are from '.$pair->min_price.' to '.$pair->max_price.'.';
            $this->addError('required_trading_rate', $errorMsg);
        }
    }

    public function allowedQuantity()
    {
        $grid = TradingGrid::findOne(['id' => $this->trading_grid_id]);
        $pair = CurrencyPair::findOne(['id' => $grid->pair_id]);
        $actualQuantity = round($grid->order_amount / $this->required_trading_rate, 6);

        if ($actualQuantity < $pair->min_quantity || $actualQuantity > $pair->max_quantity) {
            $errorMsg = 'Incorrect value of the quantity of purchased currency. Valid values are from '.$pair->min_quantity.' to '.$pair->max_quantity.'. Please change the currency rate or amount values to adjust the quantity.';
            $this->addError('required_trading_rate', $errorMsg);
        }
    }

    /*
     * Метод возвращает точное количество закупаемой валюты по ордеру,
     * рассчитывая все в соответствии с типом сетки
     */
    public static function getQuantity($order_id)
    {
        /*
         * Берем данные текущего ордера
         */
        $order = self::find()
            ->with('grid')
            ->where(['id' => $order_id])
            ->one();

        /*
         * Берем данные предыдущего ордера
         */
        $previousOrder = self::find()
            ->with('grid')
            ->where(['id' => $order->previous_order_id])
            ->one();

        /*
         * Данные сетки и валютной пары пишем в отдельные переменные
         * По валютной паре будем смотреть точность для курсов и общих сумм `price_precision`
         */
        $grid = $order->grid;
        $pair = CurrencyPair::findOne(['id' => $grid->pair_id]);

        /*
         * Если предыдущего ордера не существует,
         * количество вернем по настройке сетки.
         * А именно - разделим order_amount, заданный в сетке,
         * на необходимый курс для исполнения текущего ордера `required_trading_rate`
         */
        if (empty($previousOrder)) {
            return round($grid->order_amount / $order->required_trading_rate, $pair->price_precision);
        }

        /*
         * Если предыдущий ордер есть, при этом операции у них равны,
         * значит, что-то пошло не так. Вернем null,
         * чтобы вызвать ошибку запроса
         */
        if ($order->operation === $previousOrder->operation) {
            return null;
        }

        /*
         * Вывод количество для типа #1 - "торговать на все"
         * В этом случае:
         *  - если операция текущего ордера 'buy',
         * то разделим всю полученную в предыдущем ордере сумму (received),
         * на необходимый курс для исполнения текущего ордера `required_trading_rate`,
         *  - если же операция ордера 'sell',
         * то есть мы собираемся продавать, вернем всю сумму,
         * полученную в предыдущем ордере (received).
         */
        if ($grid->trading_method === 1) {
            return $order->operation === 'buy'
                ? round($previousOrder->received / $order->required_trading_rate, $pair->price_precision)
                : $previousOrder->received;
        }
        /*
         * Вывод количество для типа #2 - "Сохранять валюту, на которую покупаем"
         * В этом случае:
         *  - если операция текущего ордера 'buy',
         * то есть мы собираемся покупать,
         * разделим сумму покупки, заданную в сетке (order_amount),
         * на необходимый курс для исполнения текущего ордера `required_trading_rate`,
         *  - если же операция ордера 'sell',
         * то есть мы собираемся продавать, вернем всю сумму,
         * полученную в предыдущем ордере (received).
         */
        elseif ($grid->trading_method === 2) {
            return $order->operation === 'buy'
                ? round($grid->order_amount / $order->required_trading_rate, $pair->price_precision)
                : $previousOrder->received;
        }
        /*
         * Вывод количество для типа #3 - "Сохранять покупаемую валюту"
         * В этом случае:
         *  - если операция текущего ордера 'buy',
         * то есть мы собираемся покупать,
         * разделим сумму, полученную в предыдущем ордере (received),
         * на необходимый курс для исполнения текущего ордера `required_trading_rate`,
         *  - если же операция ордера 'sell',
         * то есть мы собираемся продавать, вернем сумму заданную в сетке (order_amount),
         * на необходимый курс предыдущего ордера (required_trading_rate).
         */
        elseif ($grid->trading_method === 3) {
            return $order->operation === 'buy'
                ? round($previousOrder->received / $order->required_trading_rate, $pair->price_precision)
                : round($grid->order_amount / $previousOrder->required_trading_rate, $pair->price_precision);
        }
        else {
            return null;
        }
    }

    public static function add($data, $formName = '')
    {
        $model = new self();

        if ($model->load($data, $formName) && $model->save()) {

            $logData = [
                'user_id' => $model->user_id,
                'trading_grid_id' => $model->trading_grid_id,
                'order_id' => $model->id,
                'type' => 'success',
                'message' => 'Order successfully created',
                'error_code' => null,
            ];

            OrderLog::add($logData, '');

            return true;
        }

        $error = $data['operation'] === 'buy'
            ? AppError::BUY_ORDER_CREATION_PROBLEM
            : AppError::SELL_ORDER_CREATION_PROBLEM;

        $logData = [
            'user_id' => $model->user_id,
            'trading_grid_id' => $model->trading_grid_id,
            'type' => $error['type'],
            'message' => $error['message'],
            'error_code' => $error['code'],
        ];

        TradingGridLog::add($logData, '');

        return false;
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