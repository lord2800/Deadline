<?php

use Phinx\Migration\AbstractMigration;

class Initial extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * Uncomment this method if you would like to use it.
     */
    public function change()
    {
        $this->table('users')
            ->addColumn('email', 'string')
            ->addColumn('password', 'string')
            ->addColumn('permissions', 'text', array('null' => true))
            ->addColumn('activated', 'boolean', array('default' => 0))
            ->addColumn('activation_code', 'string', array('null' => true))
            ->addColumn('activated_at', 'timestamp', array('null' => true))
            ->addColumn('last_login', 'timestamp', array('null' => true))
            ->addColumn('persist_code', 'string', array('null' => true))
            ->addColumn('reset_password_code', 'string', array('null' => true))
            ->addColumn('first_name', 'string', array('null' => true))
            ->addColumn('last_name', 'string', array('null' => true))
            ->addColumn('created_at', 'timestamp')
            ->addColumn('updated_at', 'timestamp')
            ->addIndex(array('email'), array('unique' => true))
            ->create();

        $this->table('groups')
            ->addColumn('name', 'string')
            ->addColumn('permissions', 'text', array('null' => true))
            ->addColumn('created_at', 'timestamp')
            ->addColumn('updated_at', 'timestamp')
            ->addIndex(array('name'), array('unique' => true))
            ->create();

        $this->table('users_groups')
            ->addColumn('user_id', 'integer')
            ->addColumn('group_id', 'integer')
            ->addIndex(array('user_id', 'group_id'))
            ->create();

        $this->table('throttle')
            ->addColumn('user_id', 'integer')
            ->addColumn('ip_address', 'string', array('null' => true))
            ->addColumn('attempts', 'integer', array('default' => 0))
            ->addColumn('suspended', 'boolean', array('default' => false))
            ->addColumn('banned', 'boolean', array('default' => false))
            ->addColumn('last_attempt_at', 'timestamp', array('null' => true))
            ->addColumn('suspended_at', 'timestamp', array('null' => true))
            ->addColumn('banned_at', 'timestamp', array('null' => true))
            ->create();
    }
    
    /**
     * Migrate Up.
     */
    public function up()
    {
    }

    /**
     * Migrate Down.
     */
    public function down()
    {

    }
}
