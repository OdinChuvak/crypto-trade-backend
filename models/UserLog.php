<?php

namespace app\models;

use app\helpers\FunctionBox;

class UserLog extends BaseModel
{
    public static function tableName()
    {
        return 'user_log';
    }

    public function rules()
    {
        return [
            ['user_id', 'default', 'value' => FunctionBox::getIdentityId()],
            [
                [
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
     * @return UserLog
     */
    public static function add($data, string $formName = ''): UserLog
    {
        $lastUserLog = UserLog::find()
            ->where(['user_id' => $data['user_id']])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        /* Если лог с текущей ошибкой уже существует, удалим его */
        if ($lastUserLog
            && $lastUserLog->type === 'error'
            && $lastUserLog->error_code === $data['error_code'])
        {
            UserLog::deleteAll(['id' => $lastUserLog->id]);
        }

        return parent::add($data);
    }
}