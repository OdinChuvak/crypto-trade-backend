<?php

use yii\db\Migration;

/**
 * Class m220317_194649_exchange_currency_pair_table
 */
class m220317_194649_exchange_currency_pair_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('exchange_currency_pair', [
            'id' => $this->primaryKey(),
            'exchange_id' => $this->integer()->notNull(),
            'pair_id' => $this->integer()->notNull(),
            'first_currency' => $this->string(15)->notNull(),
            'second_currency' => $this->string(15)->notNull(),
            'min_quantity' => $this->double()->notNull(),
            'max_quantity' => $this->double()->notNull(),
            'min_price' => $this->double()->notNull(),
            'max_price' => $this->double()->notNull(),
            'min_amount' => $this->double()->notNull(),
            'max_amount' => $this->double()->notNull(),
            'price_precision' => $this->tinyInteger()->notNull(),
            'commission_taker_percent' => $this->double()->notNull(),
            'commission_maker_percent' => $this->double()->notNull(),
            'is_delisted' => $this->boolean()->defaultValue(false),
            'updated_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('exchange_currency_pair');
    }
}
