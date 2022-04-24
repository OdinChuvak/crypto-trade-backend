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
        $this->createTable('exchange_rate_limits', [
            'id' => $this->primaryKey(),
            'first_currency' => $this->string(15)->notNull(),
            'second_currency' => $this->string(15)->notNull(),
            'upper_limit' => $this->double()->notNull(),
            'lower_limit' => $this->double()->notNull(),
            'updated_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('exchange_rate_limits');
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
