<?php

namespace app\commands;

use app\exceptions\ApiException;
use app\models\CurrencyPair;
use app\models\Exchange;
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
        $exchangeModels = Exchange::find()->all();
        $exchangePairsList = [];

        foreach ($exchangeModels as $exchangeModel) {
            $EXCHANGE = \app\helpers\Exchange::getClass($exchangeModel->id);

            try {
                $exchangePairsList = $EXCHANGE::getCurrencyPairsList();
            } catch (ApiException $apiException) {
                continue;
            }

            $exchangePairsListFromDB = CurrencyPair::findAll([
                'exchange_id' => $exchangeModel->id,
                'is_delisted' => false,
            ]);

            foreach ($exchangePairsList as $exKey =>$exchangePair) {
                foreach ($exchangePairsListFromDB as $dbKey => $exchangePairFromDB) {

                    $exchangePair['exchange_id'] = $exchangeModel->id;

                    /* Если в БД уже есть валютная пара, проверим, дату последнего обновления
                       Если она больше суток, обновим данные валютной пары */
                    if ($exchangePairFromDB->name === $exchangePair['name']) {
                        if (strtotime($exchangePairFromDB->updated_at) < (time() - 60*60*24)) {
                            $exchangePairFromDB->load($exchangePair, '');
                            $exchangePairFromDB->updated_at = null;
                            $exchangePairFromDB->save();
                        }

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
                CurrencyPair::add($exchangePair);
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