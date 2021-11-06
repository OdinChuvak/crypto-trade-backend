<?php

namespace app\commands;

use app\models\CurrencyPair;
use \yii\console\Controller;
use linslin\yii2\curl\Curl;

/**
 * Class CurrencyPairUpdateController
 * @package app\commands
 *
 * Обновляет список валютных пар в таблице currency_pair и настроек лимитов по ним
 */
class CurrencyPairUpdateController extends Controller
{
    public function actionIndex()
    {
        $curl = new Curl();
        $response = $curl->get('https://api.exmo.com/v1.1/pair_settings');

        if ($curl->errorCode !== null) {
            \Yii::error('Error ' . $curl->errorCode . ': ' . $curl->errorText, __METHOD__);
            exit();
        }

        $data = json_decode($response, true);

        foreach ($data as $currencyPair => $pairData)
        {
            $pair = explode('_', $currencyPair);

            $pairData['name'] = $pair[0].'/'.$pair[1];
            $pairData['first_currency'] = $pair[0];
            $pairData['second_currency'] = $pair[1];

            $pairModel = CurrencyPair::findOne([
                'first_currency' => $pair[0],
                'second_currency' => $pair[1]]) ?: new CurrencyPair();

            if (!($pairModel->load($pairData, '') && $pairModel->save())) {
                \Yii::error('Error saving data of the ' . $pairData['name'] . ' currency pair to the database', __METHOD__);
            }
        }
    }
}