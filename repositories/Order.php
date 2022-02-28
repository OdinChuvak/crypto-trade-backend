<?php

namespace app\repositories;

use app\helpers\AppError;
use app\models\OrderLog;
use app\models\TradingLineLog;
use app\models\UserLog;

class Order
{
    /**
     * Объект работы с криптобиржей Exmo
     */
    public $exmo = null;

    /**
     * В конструкторе создаем объект работы с биржей Exmo
     *
     * @param $user_id
     */
    public function __construct($user_id)
    {
        $this->exmo = new Exmo($user_id);

        /**
         * В случае ошибки $exmo = AppError::NO_AUTH_KEY_FILE
         */
        if (gettype($this->exmo) === 'array' && isset($this->exmo['type']) && $this->exmo['type'] === 'error') {

            UserLog::add([
                'user_id' => $user_id,
                'type' => $this->exmo['type'],
                'message' => $this->exmo['message'],
                'error_code' => $this->exmo['code'],
            ]);

            return false;
        }

        return $this;
    }

    /**
     * Метод отменяет все неисполненные ордера в сетке
     *
     * @param $line_id
     * @return bool
     * @throws \Exception
     */
    public function cancelGridOrders($line_id)
    {
        /**
         * Берем все неисполненные ордера сетки
         */
        $gridOrders = \app\models\Order::find()
            ->where([
                '`order`.`trading_line_id`' => $line_id,
                '`order`.`is_executed`' => false,
                '`order`.`is_canceled`' => false,
            ])
            ->all();

        /**
         * Если в сетке нет неисполненных ордеров, завершим
         */
        if (!$gridOrders) return true;

        foreach ($gridOrders as $order) {

            /**
             * Если ордер уже был размещен на бирже,
             * шлем запрос на отмену
             */
            if ($order->is_placed == true) {
                $this->exmo->cancelOrder($order->exmo_order_id);
            }

            /**
             * Ставим метку отмененного ордера и сохраняем
             */
            $order->is_canceled = true;

            $order->save();
        }

        return true;
    }

    /**
     * Метод создаст ордер в БД приложения
     * и запишет логи об успешной или неудачной операции.
     *
     * @param $orderData array массив с данными ордера
     * @param $formName string название формы данных
     *
     * @return bool вернет `true` в случае успеха и `false` в противном случае
     */
    public function createOrder(array $orderData, string $formName)
    {
        $model = new \app\models\Order();

        if ($model->load($orderData, $formName) && $model->save()) {

            OrderLog::add([
                'user_id' => $model->user_id,
                'trading_line_id' => $model->trading_line_id,
                'order_id' => $model->id,
                'type' => 'success',
                'message' => 'Order successfully created',
                'error_code' => null,
            ], '');

            return true;
        }

        $error = $model->operation === 'buy'
            ? AppError::BUY_ORDER_CREATION_PROBLEM
            : AppError::SELL_ORDER_CREATION_PROBLEM;

        TradingLineLog::add([
            'user_id' => $model->user_id,
            'trading_line_id' => $model->trading_line_id,
            'type' => $error['type'],
            'message' => $error['message'],
            'error_code' => $error['code'],
        ], '');

        return false;
    }


































    /**
     * Создание ордера(-ов), являющихся логическим продолжением ордера,
     * переданного в параметре
     *
     * @param $order_id
     * @return bool
     * @throws \yii\db\Exception
     */
    public function extension($order_id)
    {
        /*
         * Берем данные ордера, который нужно продолжить
         */
        $order = \app\models\Order::find()
            ->with('pair')
            ->joinWith('grid')
            ->where(['`order`.`id`' => $order_id])
            ->one();

        /*
         * Если ордер был отменен, вызовем return, чтобы завершить метод.
         * Ордер мог быть отменен в предыдущую итерацию, поэтому на руку такой подход,
         * где мы передаем в метод только id ордера, а на месте проверяем,
         * отменен ордер или нет
         */
        if ($order->is_canceled) {
            return false;
        }

        if ($order->operation === 'buy') {
            $this->extensionBuy($order);
        } else {
            $this->extensionSell($order);
        }

        return true;
    }

    /**
     * Создание ордеров, являющихся логическим продолжением ордера покупки
     *
     * @param $order
     * @return bool
     * @throws \yii\db\Exception
     */
    public function extensionBuy($order)
    {
        /*
         * Сформируем общие данные для лога
         * */
        $logData = [
            'user_id' => $order->user_id,
            'trading_line_id' => $order->trading_line_id,
            'order_id' => $order->id,
        ];

        /*
         * Сформируем данные "ответного" ордера на продажу
         */
        $sellOrder = [
            'user_id' => $order->user_id,
            'trading_line_id' => $order->trading_line_id,
            'previous_order_id' => $order->id,
            'operation' => 'sell',
            'required_trading_rate' => round($order->required_trading_rate + ($order->required_trading_rate * $order->line->order_step)/100, $order->pair->price_precision),
        ];

        /*
         * Сформируем данные "ответного" ордера на покупку
         */
        $newBuyOrder = [
            'user_id' => $order->user_id,
            'trading_line_id' => $order->trading_line_id,
            'operation' => 'buy',
            'required_trading_rate' => round(($order->required_trading_rate * 100) / (100 + $order->line->order_step), $order->pair->price_precision),
        ];

        /*
         * Откроем транзакцию, чтобы ответные ордера схранились либо оба, либо ниодин
         */
        $transaction = \Yii::$app->getDb()->beginTransaction();

        /*
         * Создаем ордера на продажу и покупку
         */
        if (\app\models\Order::add($sellOrder, '') &&
            \app\models\Order::add($newBuyOrder, ''))
        {
            /*
             * Если оба ответных "ордера" удалось записать в БД,
             * пометим текущий ордер как продолженный, а также,
             * запишем лог об удачной операции
             */
            $order->is_continued = true;
            $order->is_error = false;

            $logData['type'] = 'success';
            $logData['message'] = 'Order successfully continued';
            $logData['error_code'] = null;

            $order->save();
            OrderLog::add($logData, '');

            /*
             * И последнее комитим наши данные запросы в транзакции
             */
            $transaction->commit();

        } else {

            /*
             * Если что-то не удалось записать в БД,
             * пометим текущий ордер ошибкой, запишем соответствующий лог и
             * откатим оба запроса, чтобы не сохранять часть ответных ордеров.
             */
            $order->is_error = true;

            $error = AppError::ORDER_CREATION_PROBLEM;

            $logData['type'] = $error['type'];
            $logData['message'] = $error['message'];
            $logData['error_code'] = $error['error_code'];

            $order->save();
            OrderLog::add($logData, '');

            /*
             * Откатываем наши запросы в транзакции
             */
            $transaction->rollBack();
        }

        return true;
    }

    /**
     * Создание ордера, являющегося логическим продолжением ордера продажи
     *
     * @param $order
     */
    public function extensionSell($order)
    {
        /*
         * Сформируем общие данные для лога
         * */
        $logData = [
            'user_id' => $order->user_id,
            'trading_line_id' => $order->trading_line_id,
            'order_id' => $order->id,
        ];

        /*
         * Сформируем данные "ответного" ордера на покупку
         */
        $newBuyOrder = [
            'user_id' => $order->user_id,
            'trading_line_id' => $order->trading_line_id,
            'operation' => 'buy',
            'required_trading_rate' => round(($order->required_trading_rate * 100) / (100 + $order->line->order_step), $order->pair->price_precision),
        ];
    }

    /**
     * Метод отменяет активный ордер.
     *
     * @throws \Exception
     */
    public function cancelOrder($order_id)
    {
        /*
         * Берем ордер
         */
        $activeOrder = \app\models\Order::find()
            ->with('grid')
            ->where(['id' => $order_id])
            ->one();

        /*
         * Формируем общие данные для логов
         */
        $logData = [
            'user_id' => $activeOrder->user_id,
            'trading_line_id' => $activeOrder->line->id,
            'order_id' => $activeOrder->id,
        ];

            /*
             * Если ордер уже был размещен на бирже,
             */
            if ($activeOrder->is_placed) {
                /*
                 * Шлем запрос на его отмену
                 */
                $orderCancel = $this->exmo->cancelOrder($activeOrder->exmo_order_id);

                /*
                 * Если отмена завершилась успешно
                 */
                if ($orderCancel['result']) {

                    /*
                     * Получаем информацию о продажах ордера (ордер может быть продан частично)
                     */
                    $orderTrades = $this->exmo->getOrderTrades($activeOrder->exmo_order_id);

                    /*
                     * Если у отменяемого ордера были продажи, шлем его на исполнение,
                     * чтобы зафиксировать данные по продажам
                     */
                    if (isset($orderTrades['result'])
                        && !$orderTrades['result']
                        && count($orderTrades['trades']) > 0)
                    {
                        $this->execution($activeOrder->id);
                    }

                    /*
                     * Отменяем ордер и фиксируем логи
                     */
                    $activeOrder->is_canceled = true;
                    $activeOrder->is_error = false;

                    $logData['type'] = 'success';
                    $logData['message'] = 'Order was canceled';
                    $logData['error_code'] = null;

                    //TODO взять список продаж по ордеру
                    //Если список пусть, просто пометить

                } else {

                    /*
                     * Если возникла ошибка при попытке отменить ордер на бирже,
                     * помечаем активный ордер ошибкой
                     */
                    $activeOrder->is_error = true;

                    /*
                     * Достаем из ответа код ошибки и находим ошибку приложения,
                     * соответствующую ошибке из кода ответа
                     */
                    $exchange_error_code = AppError::getExchangeErrorFromMessage($orderCancel['error']);
                    $error = AppError::getMappingError($exchange_error_code);

                    /*
                     * Дополняем данные для записи в лог ордера, в случае неудачи
                     */
                    $logData['type'] = $error['type'];
                    $logData['message'] = $error['message'];
                    $logData['error_code'] = $error['code'];

                }
            } else {

                /*
                 * Если ордер не был размещен на бирже, просто помечаем его как отмененный
                 */
                $activeOrder->is_canceled = true;
                $activeOrder->is_error = false;

                $logData['type'] = 'success';
                $logData['message'] = 'Order was canceled';
                $logData['error_code'] = null;

            }

            $activeOrder->save();

        return true;
    }
}