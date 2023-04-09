<?php

namespace app\commands;

use app\exceptions\ApiException;
use app\models\Pair;
use app\models\Exchange;
use app\models\ExchangePair;
use Exception;
use \yii\console\Controller;

/**
 * Class CurrencyPairUpdateController
 * @package app\commands
 *
 * Обновляет список валютных пар в таблице currency_pair и настроек лимитов по ним
 */
class CurrencyPairUpdateController extends Controller
{
    /**
     * @throws Exception
     */
    public function actionIndex(): void
    {
        $exchangeModels = Exchange::find()
            ->where(['is_disabled' => 0])
            ->all();

        foreach ($exchangeModels as $exchangeModel) {
            $EXCHANGE = \app\services\Exchange::getClass($exchangeModel->id);

            try {
                $exchangePairsList = $EXCHANGE::getCurrencyPairsList();
            } catch (ApiException $apiException) {
                continue;
            }

            foreach ($exchangePairsList as $exchangePair) {

                $pairName = $exchangePair['first_currency'] . '/' . $exchangePair['second_currency'];
                $pair = Pair::findOne(['name' => $pairName]);

                if (!$pair) {
                    $pair = Pair::add([
                        'name' => $exchangePair['first_currency'] . '/' . $exchangePair['second_currency'],
                        'first_currency' => $exchangePair['first_currency'],
                        'second_currency' => $exchangePair['second_currency'],
                    ]);
                }

                $exchangePairFromDB = ExchangePair::findOne([
                    'exchange_id' => $exchangeModel->id,
                    'pair_id' => $pair->id,
                ]);

                if (!$exchangePairFromDB) {
                    $exchangePair['exchange_id'] = $exchangeModel->id;
                    $exchangePair['pair_id'] = $pair->id;

                    ExchangePair::add($exchangePair);
                } else {
                    $exchangePairFromDB->load($exchangePair, '');
                    $exchangePairFromDB->updated_at = null;
                    $exchangePairFromDB->save();
                }
            }

            $exchangePairsListFromDB = ExchangePair::findAll(['exchange_id' => $exchangeModel->id]);

            /**
             * Если данные по валютной паре перестали поступать и не обновлялись больше полу года,
             * считаем, что биржа провела делистинг по этой паре
             */
            foreach ($exchangePairsListFromDB as $exchangePairFromDB) {
                if (strtotime($exchangePairFromDB->updated_at) < time() - 60 * 60 * 24 * 180) {
                    $exchangePairFromDB->is_delisted = true;
                    $exchangePairFromDB->save();
                }
            }
        }
    }
}