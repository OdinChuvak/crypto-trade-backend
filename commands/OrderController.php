<?php

namespace app\commands;

use app\helpers\AppError;
use app\models\Order;
use app\models\OrderLog;
use app\models\UserLog;
use Exmo\Api\Request;

class OrderController extends Controller
{
    /**
     * Размещение ордера с параметрами:
     *
     *      [
     *          '`order`.`is_placed`' => false,
     *          '`trading_grid`.`is_archived`' => false
     *      ],
     *
     * на криптовалютной бирже EXMO
     *
     * @return bool
     * @throws \Exception
     */
    public function actionPlacement()
    {
        /*
         * Берем всех уникальных пользователей, у кого есть неразмещенные ордера
         * */
        $users = Order::find()
            ->select('`order`.`user_id` as `id`')
            ->distinct()
            ->joinWith('grid')
            ->where(['`order`.`is_placed`' => false])
            ->andWhere(['`trading_grid`.`is_archived`' => false])
            ->all();

        /*
         * Если неразмещенных ордеров нет, завершаем скрипт
         */
        if (empty($users)) {
            return true;
        }

        /*
         * В противном случае работаем с каждым юзером, у кого есть неразмещенные ордера
         */
        foreach ($users as $user) {

            /*
             * Получаем ключи доступа из класса зашитого в ядро PHP
             * */
            $key = new \Key($user->id);

            /*
             * Если ключи доступа для данного юзера не обнаружены,
             * запишем лог и перейдем к следующему
             */
            if (!$key->is_find) {

                $logData = [
                    'user_id' => $user->id,
                    'type' => AppError::NO_AUTH_KEY_FILE['type'],
                    'message' => AppError::NO_AUTH_KEY_FILE['message'],
                    'error_code' => AppError::NO_AUTH_KEY_FILE['code'],
                ];

                UserLog::add($logData);

                continue;
            }

            /*
             * Если с ключами все в порядке, берем все неразмещенные ордера юзера
             * */
            $orders = Order::find()
                ->with('pair')
                ->joinWith('grid')
                ->where(['`order`.`user_id`' => $user->id, '`order`.`is_placed`' => false])
                ->andWhere(['`trading_grid`.`is_archived`' => false])
                ->all();

            /*
             * Еще раз проверим наличие неразмещенных ордеров,
             * так как в промежутке между предыдущей и этой проверкой ордера могли быть удалены,
             * или поменять статус (проект строиться на параллельно работающих скриптах)
             *
             * Если ордеров уже нет, перейдем ко следующему юзеру
             */
            if (empty($orders)) {
                continue;
            }

            /*
             * Если неразмещенные ордера юзера на месте,
             * создаем объект для работы с api биржи Exmo
             */
            $_EXMO = new Request($key->public, $key->secret);

            /*
             * И в цикле работаем с каждым неразмещенным ордером юзера
             */
            foreach ($orders as $key => $order) {

                /*
                 * Шлем заявку на создание ордера на бирже
                 * */
                $api = $_EXMO->query('order_create', [
                    'pair' => $order->pair->first_currency . '_' . $order->pair->second_currency,
                    'quantity' => Order::getQuantity($order->id),
                    'price' => $order->required_trading_rate,
                    'type' => $order->operation,
                ]);

                /*
                 * Формируем общие данные для записи в лог ордера
                 * */
                $logData = [
                    'user_id' => $user->id,
                    'trading_grid_id' => $order->grid->id,
                    'order_id' => $order->id,
                ];

                /*
                 * Если ордер создан
                 * */
                if ($api['result']) {

                    /*
                     * Дописываем в ордер id ордера на бирже,
                     * id предыдущего ордера в приложении,
                     * ставим метку о размещении и снимаем метку ошибки,
                     * на случай, если предыдущая попытка размещения была с ошибкой
                     * */
                    $orderCommon = [
                        'exmo_order_id' => $api['order_id'],
                        'previous_order_id' => isset($orders[$key + 1]) ? $orders[$key + 1]->id : null,
                        'is_placed' => true,
                        'is_error' => false,
                    ];

                    /*
                     * Дополняем данные для записи в лог ордера, в случае успеха
                     * */
                    $logData['type'] = 'success';
                    $logData['message'] = 'The order has been successfully placed on the exchange.';
                    $logData['error_code'] = null;

                } else {

                    /*
                     * Если ордер не удалось создать на бирже,
                     * ставим метку об ошибке
                     * */
                    $orderCommon = [
                        'is_error' => true,
                    ];

                    /*
                     * Достаем из ответа код ошибки и находим ошибку приложения,
                     * соответствующую ошибке из кода ответа
                     * */
                    $exmo_error_code = AppError::getExmoErrorFromMessage($api['error']);
                    $error = AppError::getMappingError($exmo_error_code);

                    /*
                     * Дополняем данные для записи в лог ордера, в случае неудачи
                     * */
                    $logData['type'] = $error['type'];
                    $logData['message'] = $error['message'];
                    $logData['error_code'] = $error['code'];
                }

                /*
                 * Если данные ордера удалось сохранить, пишем и лог
                 * */
                if ($order->load($orderCommon, '') && $order->save()) {
                    /*
                     * Пишем лог
                     */
                    OrderLog::add($logData);
                }
            }
        }

        return true;
    }

    /**
     * Помечает ордера с параметрами:
     *
     *      [
     *          '`order`.`is_placed`' => true,
     *          '`order`.`is_executed`' => false,
     *          '`trading_grid`.`is_archived`' => false
     *      ],
     *
     * как исполненные
     *
     * @return bool
     * @throws \Exception
     */
    public function actionExecution()
    {
        /*
         * Берем всех уникальных пользователей,
         * у кого есть размещенные, но не исполненные ордера
         * */
        $users = Order::find()
            ->select('`order`.`user_id` as `id`')
            ->distinct()
            ->joinWith('grid')
            ->where(['`order`.`is_placed`' => true, '`order`.`is_executed`' => false])
            ->andWhere(['`trading_grid`.`is_archived`' => false])
            ->all();

        /*
         * Если размещенных, но не исполненнных ордеров ни у кого нет, завершаем скрипт
         */
        if (empty($users)) {
            return true;
        }

        /*
         * В противном случае работаем с каждым юзером,
         * у кого есть размещенные, но не исполненные ордера
         */
        foreach ($users as $user) {

            /*
             * Получаем ключи доступа из класса зашитого в ядро PHP
             * */
            $key = new \Key($user->id);

            /*
             * Если ключи доступа для данного юзера не обнаружены,
             * запишем лог ошибки и перейдем к следующему
             */
            if (!$key->is_find) {

                $logData = [
                    'user_id' => $user->id,
                    'type' => AppError::NO_AUTH_KEY_FILE['type'],
                    'message' => AppError::NO_AUTH_KEY_FILE['message'],
                    'error_code' => AppError::NO_AUTH_KEY_FILE['code'],
                ];

                UserLog::add($logData);

                continue;
            }

            /*
             * Если с ключами все в порядке,
             * берем все размещенные, но не исполненные ордера пользователя
             * */
            $orders = Order::find()
                ->with('pair')
                ->joinWith('grid')
                ->where([
                    '`order`.`user_id`' => $user->id,
                    '`order`.`is_placed`' => true,
                    '`order`.`is_executed`' => false
                ])
                ->andWhere(['`trading_grid`.`is_archived`' => false])
                ->all();

            /*
             * Еще раз проверим наличие размещенных, но не исполненных ордеров,
             * так как в промежутке между предыдущей и этой проверкой, ордера могли быть удалены,
             * или поменять статус (проект строиться на параллельно работающих скриптах)
             *
             * Если ордеров уже нет, перейдем ко следующему юзеру
             */
            if (empty($orders)) {
                continue;
            }

            /*
             * Если есть размещенные, но не исполненные ордера,
             * создаем объект для работы с api биржи Exmo
             */
            $_EXMO = new Request($key->public, $key->secret);

            /*
             * Шлем запрос на получение списка всех активных ордеров пользователя
             * */
            $activeOrders = $_EXMO->query('user_open_orders');

            foreach ($orders as $key => $order) {

                /*
                 * Берем название валютной пары ордера в формате XXX_YYY
                 * (используется как ключ в массиве активных ордеров)
                 * */
                $pairName = $order->pair->first_currency . '_' . $order->pair->second_currency;

                /*
                 * Если ордер найден в списке активных ордеров, то ничего не предпринимаем
                 * */
                if (!empty($activeOrders) && isset($activeOrders[$pairName])) {
                    foreach ($activeOrders[$pairName] as $activeOrder) {
                        if ($order->exmo_order_id === $activeOrder['order_id']) {
                            continue;
                        }
                    }
                }

                /*
                 * Если ордера нет в списке активных ордеров,
                 * формируем общие данные для записи в лог ордера
                 * */
                $logData = [
                    'user_id' => $user->id,
                    'trading_grid_id' => $order->grid->id,
                    'order_id' => $order->id,
                ];

                /*
                 * Шлем запрос на получение информации о продажах ордера
                 * */
                $orderTrades = $_EXMO->query(
                    'order_trades',
                    [
                        'order_id' => $order->exmo_order_id
                    ]);

                /*
                 * Если возникла ошибка, прилетит массив, где будут параметры:
                 *      [
                 *          'result' => false
                 *          'error' => 'Код и сообщение об ошибке'
                 *      ]
                 */
                if (isset($orderTrades['result']) && !$orderTrades['result']) {

                    /*
                     * Если ордера нет на бирже (пользователь вручную удалил его
                     * через интерфейс биржи, или он уже был обработан,
                     * но не зафиксирован как is_executed в базе приложения),
                     * или возникла какая-то проблема с получением
                     * информации по ордеру, ставим метку об ошибке
                     * */
                    $orderCommon = [
                        'is_error' => true,
                    ];

                    /*
                     * Достаем из ответа код ошибки и находим ошибку приложения,
                     * соответствующую ошибке из кода ответа
                     * */
                    $exmo_error_code = AppError::getExmoErrorFromMessage($orderTrades['error']);
                    $error = AppError::getMappingError($exmo_error_code);

                    /*
                     * Дополняем данные для записи в лог ордера, в случае неудачи
                     * */
                    $logData['type'] = $error['type'];
                    $logData['message'] = $error['message'];
                    $logData['error_code'] = $error['code'];

                } else {
                    /*
                     * Если же информация по продажам ордера есть,
                     * вычисляем и дописываем в ордер информацию по сделке:
                     * - актуальный курс сделки,
                     * - актуальная инвестированная сумма,
                     * - актуальная полученная сумма,
                     * - актуальная сумма комиссии
                     * */
                    $k = 0;
                    $actual_trading_rate = 0;
                    $invested = 0;
                    $received = 0;
                    $commission = 0;

                    /*
                     * Ордер мог быть продан по частям, и поэтому в нем может быть
                     * несколько продаж. В таком случае считаем средний показатель
                     * актуального курса, инвестированных и полученных средств,
                     * а также комиссии
                     * */
                    foreach ($orderTrades['trades'] as $trade) {

                        /*
                         * TODO удалить через 3 месяца
                         * Предполагается, что комиссия с продаж взимается с полученной валюты.
                         * Но это не точно. Ососбенно, учитывая, что api возвращает поле с указанием
                         * названия валюты, в которой взыскана комиссия - commission_currency.
                         * Во избежание ошибок, в случае, если комиссия может удерживаться с любой валюты,
                         * добавлю скрипт, который пометит ордер как ошибочный, и запишет лог, в случае,
                         * если валюта комиссии окажется не равной валюте покупки.
                         * (Чтобы знать, возможно ли такое)
                         * */
                        if ($trade['commission_currency'] != $order->pair->first_currency) {
                            \Yii::error('Комиссия валюты не соответствует первой валюте пары! ' . '(Валюта комиссии - '.$trade['commission_currency'].', Первая валюта пары - '.$order->pair->first_currency.', Ордер - '.$order->id.')', __METHOD__);
                        }

                        $actual_trading_rate += $trade['price'];
                        $invested += $trade['amount'];
                        $received += $order->pair->first_currency === $trade['commission_currency'] ? $trade['quantity'] - $trade['commission_amount'] : $trade['quantity'];
                        $commission += $trade['commission_amount'];
                        $k++;
                    }

                    /*
                     * Формируем окончательные данные по реализации ордера
                     */
                    $orderCommon = [
                        'actual_trading_rate' => round($actual_trading_rate / $k, $order->pair->price_precision),
                        'invested' => round($invested / $k, $order->pair->price_precision),
                        'received' => round($received / $k, $order->pair->price_precision),
                        'commission_amount' => round($commission / $k, $order->pair->price_precision),
                        'is_executed' => true,
                        'is_error' => false,
                    ];

                    /*
                     * Дополняем данные для записи в лог ордера, в случае успеха
                     * */
                    $logData['type'] = 'success';
                    $logData['message'] = 'The order has been successfully executed.';
                    $logData['error_code'] = null;
                }

                /*
                 * Если данные удалось загрузить в ордер и сохранить
                 */
                if ($order->load($orderCommon, '') && $order->save()) {

                    /*
                     * Пишем лог
                     */
                    OrderLog::add($logData);
                }
            }
        }

        return true;
    }
}