<?php

use yii\db\Migration;

/**
 * Class m230504_192011_market_dynamic_table
 */
class m230504_192011_market_dynamic_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp(): void
    {
        $this->createTable('market_dynamic', [

            'id' => $this->primaryKey(),

            'exchange_id' => $this->integer()
                ->notNull(),

            'currency' => $this->string(16)
                ->notNull(),

            'increased' => $this->tinyInteger()
                ->defaultValue(0),

            'decreased' => $this->tinyInteger()
                ->defaultValue(0),

            'unchanged' => $this->tinyInteger()
                ->defaultValue(0),

            'unaccounted' => $this->tinyInteger()
                ->defaultValue(0),

            'updated_at' => $this->timestamp()
                ->notNull()
                ->append('DEFAULT CURRENT_TIMESTAMP()'),

            'created_at' => $this->timestamp()
                ->notNull()
                ->append('DEFAULT CURRENT_TIMESTAMP()'),

        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown(): void
    {
        $this->dropTable('market_dynamic');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m230504_192011_market_dynamic_table cannot be reverted.\n";

        return false;
    }
    */
}
