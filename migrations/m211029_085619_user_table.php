<?php

use yii\db\Migration;

/**
 * Class m211029_085619_user_table
 */
class m211029_085619_user_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('user', [
            'id' => $this->primaryKey(),
            'email' => $this->string(128)->notNull(),
            'password_hash' => $this->string(128)->notNull(),
            'access_token' => $this->string(512)->null(),
            'created_at' => $this->timestamp()->notNull()->append('DEFAULT CURRENT_TIMESTAMP()'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('user');
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m211029_085619_user_table cannot be reverted.\n";

        return false;
    }
    */
}
