<?php

namespace app\commands;

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
}