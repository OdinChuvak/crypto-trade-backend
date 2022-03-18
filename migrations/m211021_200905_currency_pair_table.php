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
            'name' => $this->string(15)->notNull(),
            'updated_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('currency_pair');
    }
}
