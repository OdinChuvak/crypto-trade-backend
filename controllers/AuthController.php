<?php

namespace app\controllers;

use app\models\User;
use yii\web\UnauthorizedHttpException;

class AuthController extends BaseApiController
{
    public $modelClass = User::class;

    public function actionLogin()
    {
        $requestParams = self::getRequestParams();

        $user = (new $this->modelClass)::find()
            ->where(['email' => $requestParams['email']])
            ->one();

        if (empty($user) || !password_verify($requestParams['password'], $user->password_hash)) {
            throw new UnauthorizedHttpException();
        }

        // TODO здесь должна быть генерация токена
        $user->access_token = 'dfhasiufg;sdfuhiUFH;Dsgfuds;FGD;IS';

        if ($user->save()) {
            return [
                'id' => $user->id,
                'email' => $user->email,
                'token' => $user->access_token,
            ];
        } else {
            throw new UnauthorizedHttpException();
        }
    }

    public function actionLogout()
    {
        $identity = \Yii::$app->user->identity->getId();

        return User::updateAll(['access_token' => null], ['id' => $identity]);
    }
}