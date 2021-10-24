<?php

namespace app\controllers;

use app\actions\IndexAction;
use app\models\CurrencyPair;

class CurrencyPairController extends BaseApiController
{
    public $modelClass = CurrencyPair::class;

    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['class'] = IndexAction::class;

        return $actions;
    }
}