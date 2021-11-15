<?php

use yii\db\Migration;

/**
 * Class m211115_131105_trading_grid_log
 */
class m211115_131105_trading_grid_log extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('trading_grid_log', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'trading_grid_id' => $this->integer()->notNull(),
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
        $this->dropTable('trading_grid_log');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211115_131105_trading_grid_log cannot be reverted.\n";

        return false;
    }
    */
}
