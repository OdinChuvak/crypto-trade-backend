<?php

use yii\db\Migration;

/**
 * Class m211021_201642_trading_grid_table
 */
class m211021_201642_trading_grid_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('trading_grid', [
            'id' => $this->primaryKey(),
            'pair_id' => $this->integer()->notNull(),
            'order_step' => $this->integer()->notNull(),
            'order_amount' => $this->float()->notNull(),
            'trading_method' => $this->tinyInteger(4)->notNull()->defaultValue(1),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('trading_grid');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211021_201642_trading_grid_table cannot be reverted.\n";

        return false;
    }
    */
}
