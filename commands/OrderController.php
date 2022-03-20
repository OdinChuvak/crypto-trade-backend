<?php

namespace app\commands;

use app\exceptions\ApiException;
use app\helpers\Exchange;
use app\models\ExchangeCurrencyPair;
use app\models\Order;
use app\models\OrderLog;
use Exception;

class OrderController extends \yii\console\Controller
{
    /**
     * Поочередный запуск всех скриптов
     *
     * @return bool
     * @throws Exception
     */
    public function actionIndex(): bool
    {
        $this->placement();
        $this->execution();
        $this->extension();

        return true;
    }

    /**
     * Размещение ордера
     *
     * @return bool
     * @throws Exception
     */
    public function placement(): bool
    {
        /**
         * Берем все биржи
         */
        $exchanges = \app\models\Exchange::find()->all();

        foreach ($exchanges as $exchange) {

            /**
             * Берем всех уникальных пользователей, у кого есть неразмещенные ордера в конкретной бирже
             */
            $users = Order::find()
                ->select('`order`.`user_id` as `id`')
                ->distinct()
                ->joinWith('line')
                ->where([
                    '`order`.`is_placed`' => false,
                    '`order`.`is_canceled`' => false,
                ])
                ->andWhere([
                    '`trading_line`.`is_archived`' => false,
                    '`trading_line`.`exchange_id`' => $exchange->id,
                ])
                ->all();

            /**
             * Если неразмещенных ордеров нет, перейдем ко следующей бирже
             */
            if (empty($users)) {
                continue;
            }

            /**
             * В противном случае работаем с каждым юзером, у кого есть неразмещенные ордера на текущей бирже
             */
            foreach ($users as $user) {

                /**
                 * Получаем экземпляр биржи с авторизацией под конкретного юзера
                 */
                $EXCHANGE = Exchange::getObject($exchange->id, $user->id);

                /**
                 * Если авторизация не удалась, переходим к следующему юзеру
                 */
                if (!$EXCHANGE) {
                    continue;
                }

                /**
                 * Берем все созданные не размещенные и не отмененные ордера конкретной биржи, конкретного юзера
                 */
                $orders = Order::find()
                    ->with('pair')
                    ->joinWith('line')
                    ->where([
                        '`order`.`user_id`' => $user->id,
                        '`order`.`is_placed`' => false,
                        '`order`.`is_canceled`' => false,
                    ])
                    ->andWhere([
                        '`trading_line`.`is_archived`' => false,
                        '`trading_line`.`exchange_id`' => $exchange->id,
                    ])
                    ->orderBy('created_at')
                    ->all();

                foreach ($orders as $order) {

                    $pair = ExchangeCurrencyPair::findOne([
                        'pair_id' => $order->id,
                        'exchange_id' => $exchange->id,
                    ]);

                    /**
                     * Шлем заявку на создание ордера на бирже
                     */
                    $orderData = [
                        'pair' => $pair,
                        'quantity' => \app\helpers\Order::getQuantity($order->id),
                        'price' => $order->required_trading_rate,
                        'operation' => $order->operation,
                    ];

                    try {
                        /**
                         * Пытаемся создать ордер на бирже
                         */
                        $apiResult = $EXCHANGE->createOrder(...$orderData);

                        /**
                         * Дописываем в ордер, id ордера на бирже,
                         * ставим метку о размещении и снимаем метку ошибки,
                         * на случай, если предыдущая попытка размещения была с ошибкой
                         */
                        $order->exchange_order_id = $apiResult['exchange_order_id'];
                        $order->is_placed = true;
                        $order->is_error = false;
                        $order->placed_at = date("Y-m-d H:i:s");

                        /**
                         * Сохраняем ордер и пишем лог
                         */
                        $order->save();

                        OrderLog::add([
                            'user_id' => $user->id,
                            'trading_line_id' => $order->line->id,
                            'order_id' => $order->id,
                            'type' => 'success',
                            'message' => 'Ордер успешно размещен на бирже',
                            'error_code' => null,
                        ]);

                    } catch (ApiException $apiException) {
                        /**
                         * Если ордер не удалось создать на бирже,
                         * ставим метку об ошибке
                         */
                        $order->is_error = true;

                        /**
                         * Сохраняем и пишем лог ошибки
                         */
                        $order->save();

                        OrderLog::add([
                            'user_id' => $user->id,
                            'trading_line_id' => $order->line->id,
                            'order_id' => $order->id,
                            'type' => 'error',
                            'message' => $apiException->getMessage(),
                            'error_code' => $apiException->getCode(),
                        ]);
                    }
                }
            }

        }

        return true;
    }

    /**
     * Проверяет ордера на реализацию и помечает как исполненные
     *
     * @return bool
     * @throws Exception
     */
    public function execution(): bool
    {
        /**
         * Берем все биржи
         */
        $exchanges = \app\models\Exchange::find()->all();

        foreach ($exchanges as $exchange) {

            /**
             * Берем всех уникальных пользователей,
             * у кого есть размещенные, но не исполненные ордера в конкретной бирже
             */
            $users = Order::find()
                ->select('`order`.`user_id` as `id`')
                ->distinct()
                ->joinWith('line')
                ->where([
                    '`order`.`is_placed`' => true,
                    '`order`.`is_executed`' => false,
                    '`order`.`is_canceled`' => false,
                ])
                ->andWhere([
                    '`trading_line`.`is_archived`' => false,
                    '`trading_line`.`exchange_id`' => $exchange->id,
                ])
                ->all();

            /**
             * Если размещенных, но не исполненных ордеров ни у кого нет, переходим ко следующей бирже
             */
            if (empty($users)) {
                continue;
            }

            /**
             * В противном случае работаем с каждым юзером,
             * у кого есть размещенные, но не исполненные ордера
             */
            foreach ($users as $user) {

                /**
                 * Получаем экземпляр биржи с авторизацией под конкретного юзера
                 * А также мапер данных этой биржи
                 */
                $EXCHANGE = Exchange::getObject($exchange->id, $user->id);

                /**
                 * Если авторизация не удалась, переходим к следующему юзеру
                 */
                if (!$EXCHANGE) {
                    continue;
                }

                /**
                 * Если с биржей все в порядке, берем все размещенные,
                 * но не исполненные ордера юзера, в порядке их размещения (поле `placed_at`)
                 */
                $orders = Order::find()
                    ->getPair($exchange->id)
                    ->joinWith('line')
                    ->where([
                        '`order`.`user_id`' => $user->id,
                        '`order`.`is_placed`' => true,
                        '`order`.`is_executed`' => false,
                        '`order`.`is_canceled`' => false,
                    ])
                    ->andWhere([
                        '`trading_line`.`is_archived`' => false,
                        '`trading_line`.`exchange_id`' => $exchange->id,
                    ])
                    ->orderBy('placed_at')
                    ->all();

                /**
                 * Шлем запрос на получение списка всех активных ордеров пользователя
                 */
                try {
                    $activeOrders = $EXCHANGE->getOpenOrdersList();
                } catch (ApiException $apiException) {
                    continue;
                }

                foreach ($orders as $key => $order) {

                    /**
                     * Ищем ордер в списке активных ордеров на бирже
                     */
                    $exchangeOrderIds = array_column($activeOrders, 'exchange_order_id');

                    /**
                     * Если ордер найден в списке активных ордеров,
                     * то переходим ко следующему ордеру
                     */
                    if (in_array($order->exchange_order_id, $exchangeOrderIds)) {
                        continue 2;
                    }

                    /**
                     * Если ордера нет в списке активных ордеров,
                     * пытаемся получить список продаж по ордеру
                     */
                    try {
                        $orderTrades = $EXCHANGE->getOrderTrades($order->exchange_order_id);

                        /**
                         * Если же информация по продажам ордера есть,
                         * вычисляем и дописываем в ордер информацию по сделке:
                         * - актуальный курс сделки,
                         * - актуальная инвестированная сумма,
                         * - актуальная полученная сумма,
                         * - актуальная сумма комиссии
                         */
                        $k = 0;
                        $actual_trading_rate = 0;
                        $invested = 0;
                        $received = 0;
                        $commission = 0;

                        /**
                         * Ордер мог быть продан по частям, и поэтому в нем может быть
                         * несколько продаж. В таком случае считаем средний показатель
                         * актуального курса и комиссии, а также суммы инвестированных
                         * и полученных средств
                         */
                        foreach ($orderTrades['trades'] as $trade) {

                            /**
                             * 'invested' и 'received', в зависимости от того, какая операция была произведена,
                             * меняются местами. То есть, если в паре производится операция покупки 'buy',
                             * то инвестируемыми считаются средства во второй валюте, а получаемыми - в первой.
                             * В случае, если произведена операция продажи, то есть 'sell', инвестируемыми
                             * будут считаться средства первой валюты, а получаемыми второй.
                             *
                             * Комиссия взимается с получаемой валюты
                             */

                            $actual_trading_rate += $trade['price'];
                            $invested += $order->operation === 'buy'
                                ? $trade['amount']
                                : $trade['quantity'];
                            $received += $order->operation === 'buy'
                                ? $trade['quantity'] - $trade['commission_amount']
                                : $trade['amount'] - $trade['commission_amount'];
                            $commission += $trade['commission_amount'];
                            $k++;
                        }

                        /**
                         * Фиксируем данные продаж в ордере  и сохраняем его
                         */
                        $executionData = [
                            'actual_trading_rate' => round($actual_trading_rate / $k, $order->pair->price_precision),
                            'invested' => round($invested, $order->pair->price_precision),
                            'received' => round($received, $order->pair->price_precision),
                            'commission_amount' => round($commission / $k, $order->pair->price_precision),
                            'is_executed' => true,
                            'is_error' => false,
                            'executed_at' => date("Y-m-d H:i:s"),
                        ];

                        $order->load($executionData, '');
                        $order->save();

                        /**
                         * Не забываем зафиксировать лог
                         */
                        OrderLog::add([
                            'user_id' => $user->id,
                            'trading_line_id' => $order->line->id,
                            'order_id' => $order->id,
                            'type' => 'success',
                            'message' => 'The order has been successfully executed',
                            'error_code' => null,
                        ]);

                    } catch (ApiException $apiException) {
                        /**
                         * Если ордера нет на бирже (пользователь вручную удалил его
                         * через интерфейс биржи, или возникла какая-то проблема с получением
                         * информации по ордеру, ставим метку об ошибке и сохраняем ордер
                         */
                        $order->is_error = true;
                        $order->save();

                        OrderLog::add([
                            'user_id' => $user->id,
                            'trading_line_id' => $order->line->id,
                            'order_id' => $order->id,
                            'type' => 'error',
                            'message' => $apiException->getMessage(),
                            'error_code' => $apiException->getCode(),
                        ]);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Создает ордера-продолжения для всех исполненных ордеров
     *
     * @return bool
     * @throws Exception
     */
    public function extension(): bool
    {
        /**
         * Берем все биржи
         */
        $exchanges = \app\models\Exchange::find()->all();

        foreach ($exchanges as $exchange) {

            /**
             * Берем всех уникальных пользователей,
             * у кого есть исполненные ордера, но не продолженные ордера
             */
            $users = Order::find()
                ->select('`order`.`user_id` as `id`')
                ->distinct()
                ->joinWith('grid')
                ->where([
                    '`order`.`is_executed`' => true,
                    '`order`.`is_continued`' => false,
                    '`order`.`is_canceled`' => false,
                ])
                ->andWhere([
                    '`trading_grid`.`is_archived`' => false,
                    '`trading_line`.`exchange_id`' => $exchange->id,
                ])
                ->all();

            /**
             * Если исполненных ордеров ни у кого нет, переходим ко следующей бирже
             */
            if (empty($users)) {
                continue;
            }

            /**
             * В противном случае работаем с каждым юзером,
             * у кого есть исполненные, но не продолженные ордера
             */
            foreach ($users as $user) {

                /**
                 * Получаем экземпляр биржи с авторизацией под конкретного юзера
                 * А также мапер данных этой биржи
                 */
                $EXCHANGE = Exchange::getObject($exchange->id, $user->id);

                /**
                 * Если авторизация не удалась, переходим к следующему юзеру
                 */
                if (!$EXCHANGE) {
                    continue;
                }

                /**
                 * Берем все исполненные ордера юзера, в порядке их размещения (поле `executed_at`)
                 */
                $orders = Order::find()
                    ->with('pair')
                    ->joinWith('grid')
                    ->where([
                        '`order`.`user_id`' => $user->id,
                        '`order`.`is_executed`' => true,
                        '`order`.`is_continued`' => false,
                        '`order`.`is_canceled`' => false,
                    ])
                    ->andWhere([
                        '`trading_grid`.`is_archived`' => false,
                        '`trading_line`.`exchange_id`' => $exchange->id,
                    ])
                    ->orderBy('executed_at')
                    ->all();

                /**
                 * ...и работаем с каждым таким ордером
                 */
                foreach ($orders as $order) {

                    /**
                     * В первую очередь, отменим все неисполненные ордера в линии,
                     * так как будут созданы новые, более актуальные ордера
                     */
                    $lineOrders = Order::findAll([
                        '`order`.`trading_line_id`' => $order->trading_line_id,
                        '`order`.`is_executed`' => false,
                        '`order`.`is_canceled`' => false,
                    ]);

                    foreach ($lineOrders as $lineOrder) {

                        /**
                         * Если ордер уже был размещен на бирже,
                         * шлем запрос на отмену
                         */
                        if ($lineOrder->is_placed === true) {
                            $EXCHANGE->cancelOrder($lineOrder->exchange_order_id);
                        }

                        /**
                         * Ставим метку отмененного ордера и сохраняем
                         */
                        $lineOrder->is_canceled = true;
                        $lineOrder->save();
                    }

                    /**
                     * Теперь создадим 2 ордера, являющиеся реакцией на исполнение
                     * текущего ордера
                     */
                    Order::add([
                        'user_id' => $order->user_id,
                        'trading_line_id' => $order->trading_line_id,
                        'operation' => 'buy',
                        'required_trading_rate' => round(($order->actual_trading_rate * 100) / (100 + $order->line->order_step), $order->pair->price_precision),
                    ], '');

                    Order::add([
                        'user_id' => $order->user_id,
                        'trading_line_id' => $order->trading_line_id,
                        'operation' => 'sell',
                        'required_trading_rate' => round($order->actual_trading_rate + ($order->actual_trading_rate * $order->line->order_step) / 100, $order->pair->price_precision),
                    ], '');

                    /**
                     * Далее пометим текущий ордер как продолженный и сохраним
                     */
                    $order->is_continued = true;
                    $order->is_error = false;

                    $order->save();

                    /**
                     * Но и не забудем добавить соответствующую запись в лог
                     */
                    OrderLog::add([
                        'user_id' => $user->id,
                        'trading_line_id' => $order->line->id,
                        'order_id' => $order->id,
                        'type' => 'success',
                        'message' => 'Ордер успешно продолжен!',
                        'error_code' => null,
                    ]);
                }
            }
        }

        return true;
    }
}