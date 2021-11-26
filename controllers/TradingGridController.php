<?php

namespace app\controllers;

use app\actions\IndexAction;
use app\models\TradingGrid;

class TradingGridController extends BaseApiController
{
    public $modelClass = TradingGrid::class;

    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['class'] = IndexAction::class;

        return $actions;
    }
}