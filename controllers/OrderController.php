<?php

namespace app\controllers;

use app\models\Order;

class OrderController extends BaseApiController
{
    public $modelClass = Order::class;
}