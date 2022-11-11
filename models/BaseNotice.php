<?php

namespace app\models;

abstract class BaseNotice extends BaseModel
{
    /**
     * Расширенный метод BaseModel::add()
     * Добавлена проверка на последнее уведомление об ошибке
     * Если error_code последней ошибки совпадает с текущей,
     * уведомление не записывается, чтобы избежать дублирования
     *
     * @param $data
     * @param string $formName
     * @return BaseModel
     */
    public static function add($data, string $formName = ''): BaseModel
    {
        $lastNotice = Notice::find()
            ->where([
                'reference' => $data['reference'],
                'reference_id' => $data['reference_id'],
            ])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        /* Если лог с аналогичной ошибкой уже существует, удалим его */
        if ($lastNotice
            && $lastNotice->type === 'error'
            && $lastNotice->error_code === $data['error_code'])
        {
            Notice::deleteAll(['id' => $lastNotice->id]);
        }

        return parent::add($data);
    }
}