<?php

use yii\db\Migration;

/**
 * Class m211021_200905_currency_pair_table
 */
class m211021_200905_currency_pair_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('currency_pair', [
            'id' => $this->primaryKey(),
            'name' => $this->string(15)->unique()->notNull(),
            'first_currency' => $this->string(15)->notNull(),
            'second_currency' => $this->string(15)->notNull(),
            'min_quantity' => $this->double()->notNull(),
            'max_quantity' => $this->double()->notNull(),
            'min_price' => $this->double()->notNull(),
            'max_price' => $this->double()->notNull(),
            'min_amount' => $this->double()->notNull(),
            'max_amount' => $this->double()->notNull(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('currency_pair');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211021_200905_currency_pair_table cannot be reverted.\n";

        return false;
    }
    */
}
