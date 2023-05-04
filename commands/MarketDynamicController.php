<?php

namespace app\commands;

use app\models\ExchangeRate;
use app\models\MarketDynamic;
use app\models\Pair;
use app\models\TradingLine;
use yii\base\DynamicModel;

class MarketDynamicController extends \yii\console\Controller
{
    /**
     *
     */
    public function actionIndex(): bool
    {
        /**
         * Берем все биржи
         */
        $exchanges = \app\models\Exchange::findAll(['is_disabled' => false]);

        foreach ($exchanges as $exchange) {

            $dynamicList = [];

            $subQuery = TradingLine::find()
                ->select('pair_id')
                ->where(['exchange_id' => $exchange->id])
                ->onCondition(['is_stopped' => 0]);

            $exchangePairs = Pair::find()
                ->where(['id' => $subQuery])
                ->all();

            foreach ($exchangePairs as $exchangePair) {

                if (!key_exists($exchangePair->second_currency, $dynamicList)) {
                    $dynamicList[$exchangePair->second_currency] = [
                        'increased' => 0,
                        'decreased' => 0,
                        'unchanged' => 0,
                        'unaccounted' => 0,
                    ];
                }

                $moment = time();

                $pairRateQuery = ExchangeRate::find()
                    ->where([
                        'exchange_id' => $exchange->id,
                        'pair_id' => $exchangePair->id,
                    ])
                    ->limit(1)
                    ->orderBy(['created_at' => SORT_DESC]);

                $actualPairRate = $pairRateQuery
                    ->andWhere(['>=', 'created_at', date("Y-m-d H:i:s", $moment - ExchangeRate::ACTUAL_RATE_TIME)])
                    ->one();

                $pairRateForCalculatingDynamics = $pairRateQuery
                    ->andWhere(['<', 'created_at', date("Y-m-d H:i:s", $moment - 60 * 60 - ExchangeRate::ACTUAL_RATE_TIME)])
                    ->one();

                $dynamicLabel = match (true) {
                    !$actualPairRate || !$pairRateForCalculatingDynamics => 'unaccounted',
                    $actualPairRate->value > $pairRateForCalculatingDynamics->value => 'increased',
                    $actualPairRate->value < $pairRateForCalculatingDynamics->value => 'decreased',
                    default => 'unchanged',
                };

                $dynamicList[$exchangePair->second_currency][$dynamicLabel]++;
            }

            foreach ($dynamicList as $currency => $dynamicItem) {

                $marketDynamicModel = MarketDynamic::find()
                    ->where([
                        'exchange_id' => $exchange->id,
                        'currency' => $currency,
                    ])
                    ->one() ?: new MarketDynamic();

                $marketDynamicModel->load([
                    'exchange_id' => $exchange->id,
                    'currency' => $currency,
                    'increased' => $dynamicItem['increased'],
                    'decreased' => $dynamicItem['decreased'],
                    'unchanged' => $dynamicItem['unchanged'],
                    'unaccounted' => $dynamicItem['unaccounted'],
                ], '');

                $marketDynamicModel->save();
            }
        }

        return true;
    }
}