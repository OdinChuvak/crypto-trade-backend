<?php

namespace app\services;

use app\exchanges\ExchangeInterface;
use Exception;

class TradingLine
{
    /**
     * @throws Exception
     */
    public static function updateCommission(ExchangeInterface $exchange, \app\models\TradingLine $line): bool
    {
        /**
         * Получаем новые данные по комиссии на торговой линии
         */
        $commission = $exchange->getCommissions();

        /**
         * Обновляем данные по комиссии в БД
         */
        $line->load($commission[0], '');

        return $line->save();
    }
}