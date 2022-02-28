<?php

namespace app\controllers;

use app\actions\IndexAction;
use app\models\TradingLine;

class TradingGridController extends BaseApiController
{
    public $modelClass = TradingLine::class;

    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['class'] = IndexAction::class;

        return $actions;
    }
}