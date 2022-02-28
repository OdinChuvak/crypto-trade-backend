<?php

namespace app\models;

use app\helpers\FunctionBox;

class TradingLineLog extends BaseModel
{
    public static function tableName()
    {
        return 'trading_line_log';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'trading_line_id',
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
     * @return TradingLineLog
     */
    public static function add($data, string $formName = ''): TradingLineLog
    {
        $lastTradingLineLog = TradingLineLog::find()
            ->where(['trading_line_id' => $data['trading_line_id']])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        /* Если лог с аналогичной ошибкой уже существует, удалим его */
        if ($lastTradingLineLog
            && $lastTradingLineLog->type === 'error'
            && $lastTradingLineLog->error_code === $data['error_code'])
        {
            TradingLineLog::deleteAll(['id' => $lastTradingLineLog->id]);
        }

        return parent::add($data);
    }
}