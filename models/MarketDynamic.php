<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "market_dynamic".
 *
 * @property int $id
 * @property int $exchange_id
 * @property string $currency
 * @property int|null $increased
 * @property int|null $decreased
 * @property int|null $unchanged
 * @property int|null $unaccounted
 * @property string $updated_at
 * @property string $created_at
 */
class MarketDynamic extends BaseModel
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'market_dynamic';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['exchange_id', 'currency'], 'required'],
            [['exchange_id', 'increased', 'decreased', 'unchanged', 'unaccounted'], 'integer'],
            [['updated_at', 'created_at'], 'safe'],
            [['currency'], 'string', 'max' => 16],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'exchange_id' => 'Exchange ID',
            'currency' => 'Currency',
            'increased' => 'Increased',
            'decreased' => 'Decreased',
            'unchanged' => 'Unchanged',
            'unaccounted' => 'Unaccounted',
            'updated_at' => 'Updated At',
            'created_at' => 'Created At',
        ];
    }
}
