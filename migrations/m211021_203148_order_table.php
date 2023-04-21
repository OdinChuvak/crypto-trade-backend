<?php

use yii\db\Migration;

/**
 * Class m211021_203148_order_table
 */
class m211021_203148_order_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('order', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'exchange_order_id' => $this->bigInteger()->null(),
            'trading_line_id' => $this->integer()->notNull(),
            'operation' => $this->string(5)->notNull(),
            'required_rate' => $this->double()->notNull(),
            'actual_rate' => $this->double()->null(),
            'invested' => $this->double()->null(),
            'received' => $this->double()->null(),
            'commission_amount' => $this->double()->null(),
            'is_easy_placement' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'is_placed' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'is_executed' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'is_continued' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'is_error' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'is_canceled' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
            'placed_at' => $this->timestamp()->null(),
            'executed_at' => $this->timestamp()->null(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('order');
    }
}
