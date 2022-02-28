<?php

use yii\db\Migration;

/**
 * Class m220223_085531_exchange_table
 */
class m220223_085531_exchange_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('exchange', [
            'id' => $this->primaryKey(),
            'name' => $this->string(30)->unique()->notNull(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('exchange');
    }
}
