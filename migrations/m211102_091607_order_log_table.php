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
            'trading_grid_id' => $this->integer()->notNull(),
            'order_id' => $this->integer()->notNull(),
            'type' => $this->string(24)->notNull(),
            'message' => $this->string(512)->notNull(),
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

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211102_091607_order_log_table cannot be reverted.\n";

        return false;
    }
    */
}
