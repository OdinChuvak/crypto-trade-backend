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

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m220508_130451_exchange_rate_table cannot be reverted.\n";

        return false;
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
