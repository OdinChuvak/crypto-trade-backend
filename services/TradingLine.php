<?php

namespace app\services;

use app\enums\AppError;
use app\exchanges\ExchangeInterface;
use app\models\ExchangeRate;
use app\models\Notice;
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

    public static function checkPairRate(ExchangeRate $exchangeRate, \app\models\TradingLine $line): bool
    {
        $isCheck = (time() - strtotime($exchangeRate->updated_at)) <= ExchangeRate::ACTUAL_RATE_TIME;

        if (!$isCheck) {
            Notice::add([
                'user_id' => $line->user_id,
                'reference' => 'trading_line',
                'reference_id' => $line->id,
                'type' => AppError::OUTDATED_RATE['type'],
                'message' => AppError::OUTDATED_RATE['message'],
                'error_code' => AppError::OUTDATED_RATE['code'],
            ]);
        }

        return $isCheck;
    }
}