<?php

use yii\db\Migration;

/**
 * Class m220508_130451_exchange_rate_table
 */
class m220508_130451_exchange_rate_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('exchange_rate', [
            'id' => $this->primaryKey(),
            'exchange_id' => $this->integer()->notNull(),
            'pair_id' => $this->integer()->notNull(),
            'value' => $this->double()->notNull(),
            'rate_dynamic' => $this->tinyInteger()->defaultValue(0),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('exchange_rate');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220508_130451_exchange_rate_table cannot be reverted.\n";

        return false;
    }
    */
}
