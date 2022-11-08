<?php

use yii\db\Migration;

/**
 * Class m211021_201642_trading_line_table
 */
class m211021_201642_trading_line_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('trading_line', [
            'id' => $this->primaryKey(),
            'exchange_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'pair_id' => $this->integer()->notNull(),
            'sell_rate_step' => $this->integer()->notNull(),
            'buy_rate_step' => $this->integer()->notNull(),
            'first_order_amount' => $this->float()->notNull(),
            'is_stopped' => $this->tinyInteger(4)->notNull()->defaultValue(0),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('trading_line');
    }
}
