<?php

use yii\db\Migration;

/**
 * Class m211103_110636_user_log_table
 */
class m211103_110636_user_log_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user_log', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'type' => $this->string(24)->notNull(),
            'message' => $this->string(512)->notNull(),
            'error_code' => $this->integer()->null(),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('user_log');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211103_110636_user_log_table cannot be reverted.\n";

        return false;
    }
    */
}
