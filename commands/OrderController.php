<?php

namespace app\commands;

use app\helpers\AppError;
use app\models\Order;
use app\models\OrderLog;
use app\models\UserLog;
use Exmo\Api\Request;

class OrderController extends Controller
{
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
            ->orderBy(['`order`.`id`' => SORT_DESC])
            ->all();

        if (!empty($users)) {
            foreach ($users as $user) {

                /*
                 * Получаем ключи доступа из класса зашитого в ядро PHP
                 * */
                $key = new \Key($user->id);

                /*
                 * Если ключи доступа найдены
                 */
                if ($key->is_find) {

                    /*
                     * Берем все неразмещенные ордера пользователя
                     * */
                    $orders = Order::find()
                        ->with('pair')
                        ->joinWith('grid')
                        ->where(['`order`.`user_id`' => $user->id, '`order`.`is_placed`' => false])
                        ->andWhere(['`trading_grid`.`is_archived`' => false])
                        ->orderBy(['`order`.`id`' => SORT_DESC])
                        ->all();

                    if (!empty($orders)) {

                        /*
                         * Если есть неразмещенные ордера, создаем объект для работы с api биржи Exmo
                         */
                        $_EXMO = new Request($key->public, $key->secret);

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
                                 * Собираем данные для записи в лог ордера
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
                                 * id предыдущего ордера в приложении и ставим метку о размещении
                                 * */
                                $order->exmo_order_id = $api['order_id'];
                                $order->previous_order_id = isset($orders[$key + 1]) ? $orders[$key + 1]->id : null;
                                $order->is_placed = true;
                                $order->is_error = false;

                                /*
                                 * Дополняем данные для записи в лог ордера
                                 * */
                                $logData['type'] = 'success';
                                $logData['message'] = 'The order has been successfully placed on the exchange.';
                                $logData['error_code'] = null;

                            } else {

                                /*
                                 * Если ордер не удалось создать на бирже,
                                 * ставим метку об ошибке
                                 * */
                                $order->is_error = true;

                                /*
                                 * Достаем из ответа код ошибки и находим ошибку приложения,
                                 * соответствующую ошибке из кода ответа
                                 * */
                                $is_error = preg_match('/\d{5}/', $api['error'], $exmo_error_code);
                                $error = $is_error ? AppError::errorMap()[$exmo_error_code[0]] : AppError::UNKNOWN_ERROR;

                                /*
                                 * Дополняем данные для записи в лог ордера
                                 * */
                                $logData['type'] = $error['type'];
                                $logData['message'] = $error['message'];
                                $logData['error_code'] = $error['code'];
                            }

                            /*
                             * Если данные ордера удалось сохранить, пишем и лог
                             * */
                            if ($order->save()) {
                                OrderLog::add($logData);
                            }
                        }
                    }
                } else {

                    $logData = [
                        'user_id' => $user->id,
                        'type' => AppError::NO_AUTH_KEY_FILE['type'],
                        'message' => AppError::NO_AUTH_KEY_FILE['message'],
                        'error_code' => AppError::NO_AUTH_KEY_FILE['code'],
                    ];

                    UserLog::add($logData);
                }
            }
        }
    }
}