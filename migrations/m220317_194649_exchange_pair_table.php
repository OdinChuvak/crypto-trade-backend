<?php

use yii\db\Migration;

/**
 * Class m220317_194649_exchange_pair_table
 */
class m220317_194649_exchange_pair_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('exchange_pair', [
            'id' => $this->primaryKey(),
            'exchange_id' => $this->integer()->notNull(),
            'pair_id' => $this->integer()->notNull(),
            'min_quantity' => $this->double()->null(),
            'max_quantity' => $this->double()->null(),
            'quantity_step' => $this->double()->null(),
            'min_price' => $this->double()->null(),
            'max_price' => $this->double()->null(),
            'price_step' => $this->double()->null(),
            'min_amount' => $this->double()->null(),
            'max_amount' => $this->double()->null(),
            'price_precision' => $this->tinyInteger()->notNull(),
            'quantity_precision' => $this->tinyInteger()->notNull(),
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
        $this->dropTable('exchange_pair');
    }
}
