<?php

namespace app\controllers;

use app\models\User;
use yii\web\UnauthorizedHttpException;

class AuthenticationController extends BaseApiController
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
            return $user->access_token;
        } else {
            throw new UnauthorizedHttpException();
        }
    }

    public function actionLogout()
    {
        $user = \Yii::$app->user->identity;

        $user->access_token = null;

        return $user->save();
    }
}