<?php

use yii\db\Migration;

/**
 * Class m221111_191819_notice_table
 */
class m221111_191819_notice_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('notice', [

            'id' => $this
                ->primaryKey(),

            'user_id' => $this
                ->integer()
                ->notNull(),

            'reference' => $this
                ->string(24)
                ->notNull()
                ->comment('Ссылка на объект, к которому относится уведомление: order, trading_line, user'),

            'reference_id' => $this
                ->integer()
                ->notNull()
                ->comment('Идентификатор объекта-ссылки'),

            'type' => $this
                ->string(24)
                ->notNull()
                ->comment('Тип уведомления: success, error'),

            'message' => $this
                ->string(512)
                ->notNull(),

            'error_code' => $this
                ->integer()
                ->null(),

            'created_at' => $this
                ->timestamp()
                ->notNull()
                ->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('notice');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m221111_191819_notice_table cannot be reverted.\n";

        return false;
    }
    */
}
