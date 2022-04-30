<?php

namespace app\models;

use app\helpers\FunctionBox;

class OrderLog extends BaseModel
{
    public static function tableName(): string
    {
        return 'order_log';
    }

    public function rules(): array
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
                    'trading_line_id',
                    'order_id',
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
     * @return OrderLog
     */
    public static function add($data, string $formName = ''): OrderLog
    {
        $lastOrderLog = OrderLog::find()
            ->where(['order_id' => $data['order_id']])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        /* Если лог с аналогичной ошибкой уже существует, удалим его */
        if ($lastOrderLog
            && $lastOrderLog->type === 'error'
            && $lastOrderLog->error_code === $data['error_code'])
        {
            OrderLog::deleteAll(['id' => $lastOrderLog->id]);
        }

        return parent::add($data);
    }
}