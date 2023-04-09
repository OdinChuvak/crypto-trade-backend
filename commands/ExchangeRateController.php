<?php

namespace app\commands;

use app\exceptions\ApiException;
use app\models\Exchange;
use app\models\ExchangePair;
use app\models\ExchangeRate;
use app\models\Pair;
use app\models\TradingLine;
use Exception;

/**
 * Class ExchangeRateController
 * @package app\commands
 *
 * Пишет статистику курсов валют
 */
class ExchangeRateController extends \yii\console\Controller
{
    /**
     * Время за которое собирается статистика курсов
     */
    const EXCHANGE_RATE_STATISTIC_LIFETIME  = 60 * 60 * 24;

    /**
     * @throws Exception
     */
    public function actionIndex(): bool
    {
        /**
         * Берем список всех криптовалютных бирж
         */
        $exchangeModels = Exchange::findAll(['is_disabled' => false]);

        foreach ($exchangeModels as $exchangeModel) {

            /**
             * Берем список всех валютных пар биржи, для которых созданы торговые линии
             */
            $activePairs = TradingLine::find()
                ->select('pair_id')
                ->where(['exchange_id' => $exchangeModel->id])
                ->column();

            $pairs = Pair::find()
                ->where(['in', 'id', $activePairs])
                ->all();

            /**
             * Создаем объект работы с биржей
             */
            $EXCHANGE = \app\services\Exchange::getClass($exchangeModel->id);

            /**
             * Пробуем получить актуальную информацию по курсам валют биржи ("Бегущая строка")
             */
            try {
                $exchangeTicker = $EXCHANGE::getTicker();
            } catch (ApiException $apiException) {
                continue;
            }

            /**
             * Запишем данные по курсам в БД
             */
            foreach ($exchangeTicker as $tickerItem) {
                foreach ($pairs as $pair) {
                    if ($tickerItem['first_currency'] === $pair->first_currency
                        && $tickerItem['second_currency'] === $pair->second_currency)
                    {
                        ExchangeRate::add([
                            'exchange_id' => $exchangeModel->id,
                            'pair_id' => $pair->id,
                            'value' => $tickerItem['exchange_rate'],
                        ]);
                    }
                }
            }
        }

        ExchangeRate::deleteAll(['<', 'created_at', date("Y-m-d H:i:s", time() - self::EXCHANGE_RATE_STATISTIC_LIFETIME)]);

        return true;
    }
}