<?php

namespace app\commands;

use app\exceptions\ApiException;
use app\services\Exchange;
use app\models\ExchangePair;
use app\models\ExchangeRate;
use app\models\Notice;
use app\models\Order;
use app\models\TradingLine;
use app\utils\Math;
use Exception;

class OrderController extends \yii\console\Controller
{
    /**
     * Лимит времени в секундах, который нужно выжидать перед повторным запросом для ордеров, завершившихся с ошибкой
     */
    const ERROR_ORDER_REQUEST_TIME_LIMIT = 600;

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
        $this->renewal();

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
        $exchanges = \app\models\Exchange::findAll(['is_disabled' => false]);

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
                    '`trading_line`.`is_stopped`' => false,
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
                 * Ограничим запросы на ордера, которые в ближайшие 10 минут завершались ошибкой
                 * Например, если недостаточно средств для размещения ордера, нет смысла слать запросы
                 * на биржу каждую минуту. Это создает напрасную нагрузку.
                 *
                 * Вытащим идентификаторы ордеров, о которых в предыдущие 10 минут вносились логи ошибок
                 */
                $errorOrders = Notice::find()
                    ->select('reference_id')
                    ->where([
                        'reference' => 'order',
                        'type' => 'error',
                    ])
                    ->andWhere(['>', 'created_at', date('Y-m-d H:i:s', time() - self::ERROR_ORDER_REQUEST_TIME_LIMIT)])
                    ->distinct()
                    ->column();

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
                        '`trading_line`.`is_stopped`' => false,
                        '`trading_line`.`exchange_id`' => $exchange->id,
                    ])
                    ->andWhere(['not in', '`order`.`id`', $errorOrders])
                    ->orderBy('created_at')
                    ->all();

                foreach ($orders as $order) {

                    /**
                     * Обновим значения комиссий до актуальных
                     */
                    \app\services\TradingLine::updateCommission($EXCHANGE, $order->line);

                    /**
                     * Берем курс пары ордера (из БД)
                     */
                    $pairRate = ExchangeRate::findOne(['pair_id' => $order->pair->id]);

                    /**
                     * Проверяем актуальность курса
                     */
                    if (!\app\services\TradingLine::checkPairRate($pairRate, $order->line)) {
                        continue;
                    }

                    if (($order->operation === 'buy' && $order->required_rate >= $pairRate->value && $pairRate->dynamic === ExchangeRate::RATE_DYNAMIC_UP)
                        || ($order->operation === 'sell' && $order->required_rate <= $pairRate->value && $pairRate->dynamic === ExchangeRate::RATE_DYNAMIC_DOWN))
                    {
                        /**
                         * Формируем данные для ордера
                         */
                        $orderData = [
                            'pair' => $order->pair,
                            'quantity' => \app\services\Order::getQuantity($order),
                            'price' => $order->required_rate,
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

                            Notice::add([
                                'user_id' => $user->id,
                                'reference' => 'order',
                                'reference_id' => $order->id,
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
                             * Сохраняем и пишем уведомление ошибки
                             */
                            $order->save();

                            Notice::add([
                                'user_id' => $user->id,
                                'reference' => 'order',
                                'reference_id' => $order->id,
                                'type' => 'error',
                                'message' => $apiException->getMessage(),
                                'error_code' => $apiException->getCode(),
                            ]);
                        }
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
        $exchanges = \app\models\Exchange::findAll(['is_disabled' => false]);

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
                    '`trading_line`.`is_stopped`' => false,
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
                    ->with('pair')
                    ->joinWith('line')
                    ->where([
                        '`order`.`user_id`' => $user->id,
                        '`order`.`is_placed`' => true,
                        '`order`.`is_executed`' => false,
                        '`order`.`is_canceled`' => false,
                    ])
                    ->andWhere([
                        '`trading_line`.`is_stopped`' => false,
                        '`trading_line`.`exchange_id`' => $exchange->id,
                    ])
                    ->orderBy('placed_at')
                    ->all();

                /**
                 * Шлем запрос на получение списка всех активных ордеров пользователя
                 */
                try {
                    $exchangeOrderIds = $EXCHANGE->getOpenOrdersList();
                } catch (ApiException $apiException) {
                    continue;
                }

                foreach ($orders as $key => $order) {

                    /**
                     * Получим данные по валютной паре ордера, для данной биржи
                     */
                    $pair = ExchangePair::findOne([
                        'pair_id' => $order->line->pair_id,
                        'exchange_id' => $exchange->id,
                    ]);

                    /**
                     * Если ордер найден в списке активных ордеров,
                     * то переходим ко следующему ордеру
                     */
                    if (in_array($order->exchange_order_id, $exchangeOrderIds)) {
                        continue;
                    }

                    /**
                     * Если ордера нет в списке активных ордеров,
                     * пытаемся получить список продаж по ордеру
                     */
                    try {
                        $orderTrades = $EXCHANGE->getOrderTrades($order);

                        /**
                         * Если же информация по продажам ордера есть,
                         * вычисляем и дописываем в ордер информацию по сделке:
                         * - актуальный курс сделки,
                         * - актуальная инвестированная сумма,
                         * - актуальная полученная сумма,
                         * - актуальная сумма комиссии
                         */
                        $k = 0;
                        $actual_rate = 0;
                        $invested = 0;
                        $received = 0;
                        $commission = 0;

                        /**
                         * Ордер мог быть продан по частям, и поэтому в нем может быть
                         * несколько продаж. В таком случае считаем средний показатель
                         * актуального курса и комиссии, а также суммы инвестированных
                         * и полученных средств
                         */
                        foreach ($orderTrades as $trade) {

                            /**
                             * 'invested' и 'received', в зависимости от того, какая операция была произведена,
                             * меняются местами. То есть, если в паре производится операция покупки 'buy',
                             * то инвестируемыми считаются средства во второй валюте, а получаемыми - в первой.
                             * В случае, если произведена операция продажи, то есть 'sell', инвестируемыми
                             * будут считаться средства первой валюты, а получаемыми второй.
                             *
                             * Комиссия взимается с получаемой валюты
                             */

                            $actual_rate += $trade['price'];
                            $invested += $order->operation === 'buy'
                                ? $trade['amount']
                                : $trade['quantity'];
                            $received += $order->operation === 'buy'
                                ? $trade['quantity'] - $trade['commission_amount']
                                : $trade['amount'] - $trade['commission_amount'];
                            $commission += $trade['commission_amount'];
                            $k++;
                        }

                        $commission = $commission / $k;

                        /**
                         * Фиксируем данные продаж в ордере и сохраняем его
                         */
                        $executionData = [
                            'actual_rate' => round($actual_rate / $k, $pair->price_precision),
                            'invested' => round($invested, $order->operation === 'buy' ? $pair->price_precision : $pair->quantity_precision),
                            'received' => round($received, $order->operation === 'buy' ? $pair->price_precision : $pair->quantity_precision),
                            'commission_amount' => round($commission, $order->operation === 'buy' ? $pair->price_precision : $pair->quantity_precision),
                            'is_executed' => true,
                            'is_error' => false,
                            'executed_at' => date("Y-m-d H:i:s"),
                        ];

                        $order->load($executionData, '');
                        $order->save();

                        /**
                         * Не забываем зафиксировать уведомление
                         */
                        Notice::add([
                            'user_id' => $user->id,
                            'reference' => 'order',
                            'reference_id' => $order->id,
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

                        Notice::add([
                            'user_id' => $user->id,
                            'reference' => 'order',
                            'reference_id' => $order->id,
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
        $exchanges = \app\models\Exchange::findAll(['is_disabled' => false]);

        foreach ($exchanges as $exchange) {

            /**
             * Берем всех уникальных пользователей,
             * у кого есть исполненные ордера, но не продолженные ордера
             */
            $users = Order::find()
                ->select('`order`.`user_id` as `id`')
                ->distinct()
                ->joinWith('line')
                ->where([
                    '`order`.`is_executed`' => true,
                    '`order`.`is_continued`' => false,
                    '`order`.`is_canceled`' => false,
                ])
                ->andWhere([
                    '`trading_line`.`is_stopped`' => false,
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
                    ->joinWith('line')
                    ->where([
                        '`order`.`user_id`' => $user->id,
                        '`order`.`is_executed`' => true,
                        '`order`.`is_continued`' => false,
                        '`order`.`is_canceled`' => false,
                    ])
                    ->andWhere([
                        '`trading_line`.`is_stopped`' => false,
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
                        if ($lineOrder->is_placed) {
                            $EXCHANGE->cancelOrder($lineOrder);
                        }

                        /**
                         * Добавим в уведомление о том, что ордер отменен
                         */
                        Notice::add([
                            'user_id' => $user->id,
                            'reference' => 'order',
                            'reference_id' => $lineOrder->id,
                            'type' => 'success',
                            'message' => 'Ордер успешно отменен! (Отменивший ордер - #' . $order->id . ')',
                            'error_code' => null,
                        ]);

                        /**
                         * Ставим метку отмененного ордера и сохраняем
                         */
                        $lineOrder->is_canceled = true;
                        $lineOrder->save();
                    }

                    /**
                     * Пытаемся создать ответные ордера для текущего исполненного
                     * Ордер на покупку создаем, только если не превышен лимит ордеров на покупку на линии
                     */
                    if (\app\services\TradingLine::checkBuyOrderLimit($order->line)) {
                        \app\services\Order::createOrder($order, 'buy');
                    }

                    /**
                     * Ордер на продажу создаем только в ответ на исполненный ордер на покупку
                     */
                    if ($order->operation === 'buy') {
                        \app\services\Order::createOrder($order, 'sell');
                    }

                    /**
                     * Далее пометим текущий ордер как продолженный и сохраним
                     */
                    $order->is_continued = true;
                    $order->is_error = false;

                    $order->save();

                    /**
                     * Но и не забудем добавить соответствующую запись в уведомления
                     */
                    Notice::add([
                        'user_id' => $user->id,
                        'reference' => 'order',
                        'reference_id' => $order->id,
                        'type' => 'success',
                        'message' => 'Ордер успешно продолжен!',
                        'error_code' => null,
                    ]);
                }
            }
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function renewal()
    {
        /**
         * Берем все биржи
         */
        $exchanges = \app\models\Exchange::findAll(['is_disabled' => false]);

        foreach ($exchanges as $exchange) {

            /**
             * Берем все торговые линии конкретной биржи
             */
            $lines = TradingLine::find()
                ->with(['lastExecutedOrder', 'exchangeRate'])
                ->where([
                    'exchange_id' => $exchange->id,
                    'is_stopped' => 0,
                ])
                ->all();

            foreach ($lines as $line) {

                /**
                 * Если нет последнего исполненного ордера, переходим к следующей линии
                 */
                if (!$line->lastExecutedOrder) {
                    continue;
                }


                /**
                 * Если последний исполненный ордер линии - на покупку
                 */
                if ($line->lastExecutedOrder->operation === 'buy') {

                    /**
                     * Если последний исполненный ордер был продолжен,
                     * то есть уже должны были быть созданы ответные ордера
                     */
                    if ($line->lastExecutedOrder->is_continued) {

                        /**
                         * Берем последний ордер на покупку, созданный после последнего исполненного ордера на покупку
                         * (если был превышен лимит то такой ордер не был создан)
                         */
                        $lastCreatedBuyOrder = Order::find()
                            ->where(['`order`.`trading_line_id`' => $line->id])
                            ->andWhere(['>', '`order`.`id`', $line->lastExecutedOrder->id])
                            ->onCondition([
                                '`order`.`is_executed`' => false,
                                '`order`.`is_canceled`' => false,
                                '`order`.`operation`' => 'buy',
                            ])
                            ->one();

                        /**
                         * Если нет ордера на покупку после последнего исполненного и продолженного ордера,
                         * делаем вывод, что ордер на покупку не был создан по причине достижения лимита
                         * ордеров на покупку \app\models\Order::buy_order_limit
                         *
                         * В таком случае проверяем, не задавалось ли ручное разрешение на создание ордера на покупку.
                         * Если оно задавалось, создадим ордер на покупку.
                         */
                        if (!$lastCreatedBuyOrder && $line->manual_resolve_buy_order) {

                            /**
                             * Создаем ордер на покупку для последнего исполненного ордера
                             */
                            \app\services\Order::createOrder($line->lastExecutedOrder, 'buy');

                            /**
                             * Отключим ручное разрешение создания ордера на покупку на линии
                             */
                            $line->manual_resolve_buy_order = false;
                            $line->save();
                        }
                    }

                    continue;
                }

                /**
                 * Проверяем актуальность курса для линии
                 */
                if (!\app\services\TradingLine::checkPairRate($line->exchangeRate, $line)) {
                    continue;
                }

                /**
                 * Проверим вырос ли курс от курса последней продажи более чем на шаг TradingLine::sell_rate_step
                 */
                if (($line->lastExecutedOrder->actual_rate + Math::getPercent($line->lastExecutedOrder->actual_rate, $line->sell_rate_step)) > $line->exchangeRate->value) {
                    continue;
                }

                /**
                 * Если курс все же пошел "сильно" выше
                 * Получаем экземпляр биржи с авторизацией под конкретного юзера
                 */
                $EXCHANGE = Exchange::getObject($exchange->id, $line->user_id);

                /**
                 * Если авторизация не удалась, переходим к следующей торговой линии
                 */
                if (!$EXCHANGE) {
                    continue;
                }

                /**
                 * В первую очередь, отменим все неисполненные ордера в линии,
                 * так как будут созданы новые, более актуальные ордера
                 */
                $lineOrders = Order::findAll([
                    '`order`.`trading_line_id`' => $line->id,
                    '`order`.`is_executed`' => false,
                    '`order`.`is_canceled`' => false,
                ]);

                foreach ($lineOrders as $lineOrder) {

                    /**
                     * Если ордер уже был размещен на бирже,
                     * шлем запрос на отмену
                     */
                    if ($lineOrder->is_placed) {
                        $EXCHANGE->cancelOrder($lineOrder);
                    }

                    /**
                     * Добавим в уведомление о том, что ордер отменен
                     */
                    Notice::add([
                        'user_id' => $lineOrder->user_id,
                        'reference' => 'order',
                        'reference_id' => $lineOrder->id,
                        'type' => 'success',
                        'message' => 'Ордер успешно отменен!',
                        'error_code' => null,
                    ]);

                    /**
                     * Ставим метку отмененного ордера и сохраняем
                     */
                    $lineOrder->is_canceled = true;
                    $lineOrder->save();
                }

                /**
                 * Создадим новый ордер на покупку
                 */
                $newBuyOrder = Order::add([
                    'user_id' => $line->user_id,
                    'trading_line_id' => $line->id,
                    'operation' => 'buy',
                    'required_rate' => $line->exchangeRate->value
                ]);

                /**
                 * Но и не забудем добавить соответствующую запись в уведомления
                 */
                Notice::add([
                    'user_id' => $newBuyOrder->user_id,
                    'reference' => 'order',
                    'reference_id' => $newBuyOrder->id,
                    'type' => 'success',
                    'message' => 'Ордер успешно создан!',
                    'error_code' => null,
                ]);
            }
        }
    }
}