<?php

use yii\db\Migration;

/**
 * Class m211102_091607_order_log_table
 */
class m211102_091607_order_log_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('order_log', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'trading_line_id' => $this->integer()->notNull(),
            'order_id' => $this->integer()->notNull(),
            'type' => $this->string(24)->notNull(),
            'message' => $this->string(512)->notNull(),
            'error_code' => $this->integer()->null(),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('order_log');
    }
}
