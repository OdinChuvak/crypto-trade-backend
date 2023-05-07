<?php

namespace app\services;

use app\models\MarketDynamic;
use app\models\Pair;

class MarketService
{
    const TIME_TO_COMPARE_MARKET_DYNAMICS = 60 * 60;

    public static function isNegativeMarketDynamic(\app\models\TradingLine $line): bool
    {
        /** @var $targetCurrency - целевая валюта в торговой паре (вторая в паре) */
        $targetCurrency = Pair::find()
            ->select('second_currency')
            ->where(['id' => $line->pair_id])
            ->scalar();

        /** @var $marketDynamic - состояние рынка по валюте */
        $marketDynamic = MarketDynamic::find()
            ->where([
                'currency' => $targetCurrency,
                'exchange_id' => $line->exchange_id,
            ])
            ->one();

        return $marketDynamic->increased < $marketDynamic->decreased;
    }
}