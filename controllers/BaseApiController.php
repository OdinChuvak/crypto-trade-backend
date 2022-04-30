<?php

namespace app\controllers;

use yii\filters\auth\HttpBearerAuth;
use yii\filters\Cors;
use yii\rest\ActiveController;

class BaseApiController extends ActiveController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        /* В родителе задан аутентификатор. Удалим его, чтобы
           заголовки Cors креплялись к ответу до проверки авторизации,
           а ниже установим собственный аунтентификатор */
        unset($behaviors['authenticator']);

        $behaviors['corsFilter'] = [
            'class' => Cors::class,
        ];

        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            'except' => ['options', 'login'],
        ];

        return $behaviors;
    }

    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
    ];

    public static function getRequestParams(): object|array
    {
        $requestParams = \Yii::$app->getRequest()->getBodyParams();
        if (empty($requestParams)) {
            $requestParams = \Yii::$app->getRequest()->getQueryParams();
        }

        return $requestParams;
    }
}