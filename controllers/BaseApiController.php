<?php

namespace app\controllers;

use yii\rest\ActiveController;
use yii\rest\Serializer;

class BaseApiController extends ActiveController
{
    public $serializer = [
        'class' => 'yii\rest\Serializer',
        'collectionEnvelope' => 'items',
    ];
}