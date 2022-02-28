<?php

use yii\db\Migration;

/**
 * Class m211115_131105_trading_line_log
 */
class m211115_131105_trading_line_log extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('trading_line_log', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'trading_line_id' => $this->integer()->notNull(),
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
        $this->dropTable('trading_line_log');
    }
}
