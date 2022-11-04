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
    public function actionIndex()
    {
        $exchangeModels = Exchange::find()
            ->where(['is_disabled' => 0])
            ->all();

        $exchangePairsList = [];

        foreach ($exchangeModels as $exchangeModel) {
            $EXCHANGE = \app\helpers\Exchange::getClass($exchangeModel->id);

            try {
                $exchangePairsList = $EXCHANGE::getCurrencyPairsList();
            } catch (ApiException $apiException) {
                continue;
            }

            $exchangePairsListFromDB = ExchangePair::find()
                ->with('pair')
                ->where([
                    'exchange_id' => $exchangeModel->id,
                    'is_delisted' => false,
                ])
                ->all();

            foreach ($exchangePairsList as $exKey => $exchangePair) {
                foreach ($exchangePairsListFromDB as $dbKey => $exchangePairFromDB) {

                    $pairName = $exchangePair['first_currency'] . '/' . $exchangePair['second_currency'];

                    /* Если в БД уже есть валютная пара */
                    if ($exchangePairFromDB->pair->name === $pairName) {

                        $exchangePair['exchange_id'] = $exchangeModel->id;
                        $exchangePair['pair_id'] = $exchangePairFromDB->pair->id;

                        $exchangePairFromDB->load($exchangePair, '');
                        $exchangePairFromDB->updated_at = null;
                        $exchangePairFromDB->save();

                        /* Удалим, чтобы узнать, каких пар нет в БД */
                        unset($exchangePairsList[$exKey]);

                        /* Удалим, чтобы знать, для каких валютных пар был произведен делистинг */
                        unset($exchangePairsListFromDB[$dbKey]);

                        continue 2;
                    }
                }
            }

            /* Запишем новые валютные пары */
            foreach ($exchangePairsList as $exchangePair) {
                $newPair = Pair::add([
                    'name' => $exchangePair['first_currency'] . '/' . $exchangePair['second_currency'],
                    'first_currency' => $exchangePair['first_currency'],
                    'second_currency' => $exchangePair['second_currency'],
                ]);

                $exchangePair['exchange_id'] = $exchangeModel->id;
                $exchangePair['pair_id'] = $newPair->id;

                ExchangePair::add($exchangePair);
            }

            /* Если данные по валютной паре перестали поступать и не обновлялись больше полу года,
                считаем, что биржа провела делистинг по этой паре */
            foreach ($exchangePairsListFromDB as $exchangePairFromDB) {
                if (strtotime($exchangePairFromDB->updated_at) < time() - 60*60*24*180) {
                    $exchangePairFromDB->is_delisted = true;
                    $exchangePairFromDB->save();
                }
            }
        }
    }
}