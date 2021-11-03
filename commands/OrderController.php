<?php

namespace app\commands;

use app\models\Order;
use app\models\OrderLog;
use Exmo\Api\Request;
use yii\console\Controller;

class OrderController extends Controller
{
    public function actionPlacement()
    {
        $orders = Order::find()
            ->where(['is_placed' => false, 'is_archived' => false])
            ->with(['grid', 'pair'])
            ->all();

        if (!empty($orders)) {
            foreach($orders as $order) {

                $api = new Request($_ENV['API_KEY'], $_ENV['API_SECRET']);

                $res = $api->query('order_create', Array(
                    'pair' => $order->pair->first_currency . "_" . $order->pair->second_currency,
                    'quantity' => round($order->grid->order_amount/$order->required_trading_rate, 7),
                    'price' => $order->required_trading_rate,
                    'type' => $order->operation
                ));

                $db = \Yii::$app->db;
                $transaction = $db->beginTransaction();

                $log = new OrderLog();
                $log->order_id = $order->id;

                if ($res->result) {
                    $order->exmo_order_id = $res['order_id'];
                    $order->is_placed = true;
                    $order->is_error = false;

                    $log->type = 'success';
                    $log->message = 'Order has been successfully placed';
                } else {
                    $order->is_error = true;

                    $log->type = 'error';
                    $log->message = $res['error'];
                }

                if ($order->save() && $log->save()) {
                    $transaction->commit();
                } else {
                    $transaction->rollBack();
                }
            }
        }
    }
}