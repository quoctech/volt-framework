<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateWorkflowTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'name'              => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'             => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'is_active'         => ['type' => 'SMALLINT', 'default' => 1],
            'states_order'      => ['type' => 'JSONB', 'default' => '[]'],
            'custom_attributes' => ['type' => 'JSONB', 'default' => '{}'],
            'owner'             => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'system'],
            'creation'          => ['type' => 'TIMESTAMP', 'null' => false],
            'modified'          => ['type' => 'TIMESTAMP', 'null' => false],
        ]);
        $this->forge->addKey('name', true);
        $this->forge->addForeignKey('entity', 'sys_entity', 'name', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sys_workflow', true);
        $this->db->query('ALTER TABLE sys_workflow ALTER COLUMN creation SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE sys_workflow ALTER COLUMN modified SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS idx_workflow_active_entity ON sys_workflow(entity) WHERE is_active = 1');

        $this->forge->addField([
            'name'              => ['type' => 'VARCHAR', 'constraint' => 100],
            'workflow'          => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'             => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'docstatus'         => ['type' => 'SMALLINT', 'default' => 0],
            'allow_edit'        => ['type' => 'SMALLINT', 'default' => 1],
            'is_final'          => ['type' => 'SMALLINT', 'default' => 0],
            'color'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'gray'],
            'idx'               => ['type' => 'INTEGER', 'default' => 0],
            'custom_attributes' => ['type' => 'JSONB', 'default' => '{}'],
        ]);
        $this->forge->addPrimaryKey(['workflow', 'name']);
        $this->forge->addForeignKey('workflow', 'sys_workflow', 'name', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sys_workflow_state', true);

        $this->forge->addField([
            'name'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'    => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'icon'     => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => ''],
            'requires_comment' => ['type' => 'SMALLINT', 'default' => 0],
        ]);
        $this->forge->addKey('name', true);
        $this->forge->createTable('sys_workflow_action', true);

        $this->db->table('sys_workflow_action')->insertBatch([
            ['name' => 'submit',     'label' => 'Submit',     'icon' => 'send',    'requires_comment' => 0],
            ['name' => 'approve',    'label' => 'Approve',   'icon' => 'check',   'requires_comment' => 0],
            ['name' => 'reject',     'label' => 'Reject',    'icon' => 'x',       'requires_comment' => 1],
            ['name' => 'send_back',  'label' => 'Send Back', 'icon' => 'undo',    'requires_comment' => 1],
            ['name' => 'cancel',     'label' => 'Cancel',    'icon' => 'x-circle','requires_comment' => 0],
            ['name' => 'amend',      'label' => 'Amend',     'icon' => 'copy',    'requires_comment' => 0],
        ]);

        $this->forge->addField([
            'name'               => ['type' => 'VARCHAR', 'constraint' => 100],
            'workflow'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'from_state'         => ['type' => 'VARCHAR', 'constraint' => 100],
            'to_state'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'action'             => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'              => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => ''],
            'allowed_roles'      => ['type' => 'JSONB', 'default' => '[]'],
            'required_condition' => ['type' => 'TEXT', 'default' => ''],
            'idx'                => ['type' => 'INTEGER', 'default' => 0],
            'custom_attributes'  => ['type' => 'JSONB', 'default' => '{}'],
        ]);
        $this->forge->addPrimaryKey(['workflow', 'from_state', 'action']);
        $this->forge->addForeignKey('workflow', 'sys_workflow', 'name', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('action', 'sys_workflow_action', 'name', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sys_workflow_transition', true);
    }

    public function down()
    {
        $this->forge->dropTable('sys_workflow_transition', true);
        $this->forge->dropTable('sys_workflow_action', true);
        $this->forge->dropTable('sys_workflow_state', true);
        $this->forge->dropTable('sys_workflow', true);
    }
}
