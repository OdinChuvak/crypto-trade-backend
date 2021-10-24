<?php

namespace app\actions;

use app\controllers\OrderController;
use app\models\Order;
use Yii;
use yii\base\Exception;
use yii\helpers\Url;
use yii\rest\CreateAction;
use yii\web\ServerErrorHttpException;

class createTradingGridAction extends CreateAction
{
    /**
     * Creates a new model.
     * @return \yii\db\ActiveRecordInterface the model newly created
     * @throws ServerErrorHttpException if there is any error when creating the model
     */
    public function run()
    {
        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id);
        }

        if (!property_exists($this->controller, 'orderModelClass')) {
            throw new Exception('No orderModelClass property found in controller '.get_class($this->controller).'.');
        }

        /* @var $model \yii\db\ActiveRecord */
        $model = new $this->modelClass([
            'scenario' => $this->scenario,
        ]);

        $firstOrderModel = new $this->controller->orderModelClass([
            'scenario' => $this->scenario,
        ]);

        $bodyParams = Yii::$app->getRequest()->getBodyParams();

        $model->load($bodyParams, '');

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        if ($model->save()) {
            $id = implode(',', array_values($model->getPrimaryKey(true)));
            $bodyParams['trading_grid_id'] = $id;

            $firstOrderModel->load($bodyParams, '');

            if ($firstOrderModel->save()) {
                $response = Yii::$app->getResponse();
                $response->setStatusCode(201);
                $response->getHeaders()->set('Location', Url::toRoute([$this->viewAction, 'id' => $id], true));
                $transaction->commit();
            } else {
                $transaction->rollBack();
            }
        } elseif (!$model->hasErrors()) {
            throw new ServerErrorHttpException('Failed to create the object for unknown reason.');
        }

        if ($model->hasErrors() || (!$model->hasErrors() && !$firstOrderModel->hasErrors())) {
            return $model;
        } else {
            return $firstOrderModel;
        }


    }
}