<?php

namespace app\commands;

use app\exceptions\ApiException;
use app\models\Exchange;
use app\models\ExchangePair;
use app\models\ExchangeRate;
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
     * TODO: Временная фича
     *
     * На этапе разработки приложения, пока я не могу позволить себе серьезные мощности,
     * статистика курсов валют будет собираться только для пар, для которых есть созданная
     * торговая линия, с шагом в 10 минут. В последующем нужно переписать эту команду,
     * для сбора статистики курсов для всех валют каждой биржи с частотой 1 раз в минуту.
     *
     * @throws Exception
     */
    public function actionIndex()
    {
        /**
         * Берем список всех криптовалютных бирж
         */
        $exchangeModels = Exchange::find()->all();

        foreach ($exchangeModels as $exchangeModel) {

            /**
             * Берем список всех валютных пар биржи, для которых созданы торговые линии
             */
            $pairs = ExchangePair::find()
                ->where(['exchange_id' => $exchangeModel->id])
                ->andWhere(['in', 'pair_id', TradingLine::find()->select('pair_id')->column()])
                ->all();

            /**
             * Создаем объект работы с биржей
             */
            $EXCHANGE = \app\helpers\Exchange::getClass($exchangeModel->id);

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
                        $exchangeRate = new ExchangeRate();

                        $exchangeRate->load([
                            'exchange_id' => $exchangeModel->id,
                            'pair_id' => $pair->pair_id,
                            'first_currency' => $tickerItem['first_currency'],
                            'second_currency' => $tickerItem['second_currency'],
                            'value' => $tickerItem['exchange_rate'],
                        ], '');

                        $exchangeRate->save();
                    }
                }
            }
        }

        return true;
    }
}