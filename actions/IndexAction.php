<?php

namespace app\actions;

use Yii;
use yii\data\ActiveDataProvider;

class IndexAction extends \yii\rest\IndexAction
{
    protected function prepareDataProvider()
    {
        $requestParams = Yii::$app->getRequest()->getBodyParams();
        if (empty($requestParams)) {
            $requestParams = Yii::$app->getRequest()->getQueryParams();
        }

        $filter = null;
        if ($this->dataFilter !== null) {
            $this->dataFilter = Yii::createObject($this->dataFilter);
            if ($this->dataFilter->load($requestParams)) {
                $filter = $this->dataFilter->build();
                if ($filter === false) {
                    return $this->dataFilter;
                }
            }
        }

        if ($this->prepareDataProvider !== null) {
            return call_user_func($this->prepareDataProvider, $this, $filter);
        }

        /* @var $modelClass \yii\db\BaseActiveRecord */
        $modelClass = $this->modelClass;

        $query = $modelClass::find();

        if (!empty($filter)) {
            $query->andWhere($filter);
        }
        if (is_callable($this->prepareSearchQuery)) {
            $query = call_user_func($this->prepareSearchQuery, $query, $requestParams);
        }

        return Yii::createObject([
            'class' => ActiveDataProvider::class,
            'query' => $query,
            'pagination' => [
                'pageSizeParam' => 'pageSize',
                'pageSizeLimit' => false,
                'pageSize' => $requestParams['pageSize'],
            ],
            'sort' => [
                'params' => $requestParams,
            ],
        ]);
    }
}