<?php

namespace app\services;

use app\helpers\Exchange;
use Exception;

class TradingLine
{
    /**
     * @throws Exception
     */
    public static function updateCommission(\app\models\TradingLine $line): bool
    {
        /**
         * Поднимаем биржу, в которой выставлена торговая линия и авторизовываемся
         */
        $EXCHANGE = Exchange::getObject($line->exchange_id, $line->user_id);

        /**
         * Получаем новые данные по комиссии на торговой линии
         */
        $commission = $EXCHANGE->getCommissions();

        /**
         * Обновляем данные по комиссии в БД
         */
        $line->load($commission[0], '');

        return $line->save();
    }
}