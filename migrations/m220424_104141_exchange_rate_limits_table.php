<?php

use yii\db\Migration;

/**
 * Class m220424_104141_exchange_rate_limits_table
 */
class m220424_104141_exchange_rate_limits_table extends Migration
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
        echo "m220424_104141_exchange_rate_limits_table cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m220424_104141_exchange_rate_limits_table cannot be reverted.\n";

        return false;
    }
    */
}
