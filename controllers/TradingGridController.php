<?php

namespace app\controllers;

use app\actions\createTradingGridAction;
use app\actions\IndexAction;
use app\models\Order;
use app\models\TradingGrid;

class TradingGridController extends BaseApiController
{
    public $modelClass = TradingGrid::class;
    public $orderModelClass = Order::class;

    public function actions()
    {
        $actions = parent::actions();

        $actions['index']['class'] = IndexAction::class;
        $actions['create']['class'] = createTradingGridAction::class;

        return $actions;
    }
}