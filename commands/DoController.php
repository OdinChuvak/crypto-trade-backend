<?php

namespace app\commands;

use app\models\Order;
use app\models\TradingLine;
use app\models\User;
use yii\base\UserException;
use yii\console\Controller;
use yii\db\Exception;
use yii\web\NotFoundHttpException;

class DoController extends Controller
{
    /**
     * @param $email
     * @param $password
     *
     * @throws NotFoundHttpException
     */
    public function actionSetUserPassword($email, $password): bool
    {
        $user = User::findOne(['email' => $email]);

        if (!$user) throw new UserException('Пользователь не найден!');

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $user->password_hash = $password_hash;

        if ($user->save()) {
            return true;
        } else {
            throw new Exception('Ошибка записи данных в БД');
        }
    }

    /**
     * Создает ордера на покупку по актуальному курсу для новых активных торговых линий
     *
     * @return bool
     */
    public function actionCreateFirstOrders(): bool
    {
        $activeTradingLines = Order::find()
            ->distinct()
            ->select('trading_line_id')
            ->column();

        $newActiveTradingLines = TradingLine::find()
            ->with('exchangeRates')
            ->where(['NOT IN', 'id', $activeTradingLines])
            ->andWhere(['is_stopped' => 0])
            ->all();

        foreach($newActiveTradingLines as $newActiveTradingLine) {

            $actualExchangeRate = $newActiveTradingLine->exchangeRates[0];

            $newOrderData = [
                'user_id' => $newActiveTradingLine->user_id,
                'exchange_order_id' => null,
                'trading_line_id' => $newActiveTradingLine->id,
                'operation' => 'buy',
                'required_rate' => $actualExchangeRate->value,
                'actual_rate' => null,
                'invested' => null,
                'received' => null,
                'commission_amount' => null,
                'is_placed' => 0,
                'is_executed' => 0,
                'is_continued' => 0,
                'is_error' => 0,
                'is_canceled' => 0,
                'created_at' => time(),
                'placed_at' => null,
                'executed_at' => null,
            ];

            Order::add($newOrderData);
        }

        return true;
    }
}