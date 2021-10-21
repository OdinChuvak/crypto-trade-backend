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
            'exmo_order_id' => $this->integer()->null(),
            'trading_grid_id' => $this->integer()->notNull(),
            'previous_order_id' => $this->integer()->null(),
            'operation' => $this->string(5)->notNull(),
            'required_trading_rate' => $this->float()->notNull(),
            'actual_trading_rate' => $this->float()->null(),
            'invested' => $this->float()->null(),
            'received' => $this->float()->null(),
            'is_placed' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'is_executed' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('order');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211021_203148_order_table cannot be reverted.\n";

        return false;
    }
    */
}
