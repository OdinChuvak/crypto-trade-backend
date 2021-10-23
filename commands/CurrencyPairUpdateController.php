<?php

namespace app\commands;

use app\models\CurrencyPair;
use \yii\console\Controller;
use linslin\yii2\curl\Curl;

class CurrencyPairUpdateController extends Controller
{
    public function actionIndex()
    {
        $curl = new Curl();
        $response = $curl->get('https://api.exmo.com/v1.1/ticker');

        if ($curl->errorCode !== null) {
            exit('Error ' . $curl->errorCode . ':' . $curl->errorText);
        }

        $data = json_decode($response, true);

        foreach ($data as $currencyPair => $info)
        {
            $pair = explode('_', $currencyPair);

            $pairModel = CurrencyPair::findOne(['first_currency' => $pair[0], 'second_currency' => $pair[1]]);

            if (!$pairModel)
            {
                $pairModel = new CurrencyPair();

                $pairModel->name = $pair[0].'/'.$pair[1];
                $pairModel->first_currency = $pair[0];
                $pairModel->second_currency = $pair[1];

                $pairModel->save();
            }
        }
    }
}