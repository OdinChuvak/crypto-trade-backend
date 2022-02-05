<?php

namespace app\models;

use app\helpers\FunctionBox;

class TradingGridLog extends BaseModel
{
    public static function tableName()
    {
        return 'trading_grid_log';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'trading_grid_id',
                    'type',
                    'message'
                ],
                'required',
                'message' => 'The value cannot be empty.',
            ],
            ['error_code', 'integer'],
        ];
    }

    /**
     * Расширенный метод BaseModel::add()
     * Добавлена проверка на последний лог ошибки
     * Если error_code последней ошибки совпадает с текущей, лог не записывается,
     * чтобы избежать дублирования логов ошибок
     *
     * @param $data
     * @param string $formName
     * @return TradingGridLog
     */
    public static function add($data, string $formName = ''): TradingGridLog
    {
        $lastTradingGridLog = TradingGridLog::find()
            ->where(['trading_grid_id' => $data['trading_grid_id']])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        /* Если лог с аналогичной ошибкой уже существует, удалим его */
        if ($lastTradingGridLog
            && $lastTradingGridLog->type === 'error'
            && $lastTradingGridLog->error_code === $data['error_code'])
        {
            TradingGridLog::deleteAll(['id' => $lastTradingGridLog->id]);
        }

        return parent::add($data);
    }
}