<?php

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVoltBaseTables extends Migration
{
    // HẰNG SỐ ĐỊNH DANH TÊN BẢNG HỆ THỐNG - CLEAN CODE & NO MAGIC STRINGS
    const T_ENTITY      = 'sys_entity';
    const T_FIELD       = 'sys_entity_field';
    const T_CUSTOM      = 'sys_entity_custom';
    const T_USER        = 'sys_user';
    const T_PERMISSION  = 'sys_permission';
    const T_SEQUENCE    = 'sys_sequence';
    const T_AUDIT       = 'sys_audit_trail';
    const T_QUEUE       = 'sys_queue_job';

    public function up()
    {
        // ==========================================
        // 1. BẢNG: sys_entity
        // ==========================================
        $this->forge->addField([
            'name'              => ['type' => 'VARCHAR', 'constraint' => 100],
            'module'            => ['type' => 'VARCHAR', 'constraint' => 50],
            'issingle'          => ['type' => 'SMALLINT', 'default' => 0],
            'istable'           => ['type' => 'SMALLINT', 'default' => 0],
            'autoname'          => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'HASH'],
            'states'            => ['type' => 'JSONB', 'default' => '{}'],
            'custom_attributes' => ['type' => 'JSONB', 'default' => '{}'],
        ]);
        $this->forge->addKey('name', true);
        $this->forge->createTable(self::T_ENTITY, true); // Tham số true tương đương IF NOT EXISTS cho bảng

        // ==========================================
        // 2. BẢNG: sys_entity_field
        // ==========================================
        $this->forge->addField([
            'id'         => ['type' => 'SERIAL'],
            'parent'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'fieldname'  => ['type' => 'VARCHAR', 'constraint' => 100],
            'label'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'fieldtype'  => ['type' => 'VARCHAR', 'constraint' => 50],
            'length'     => ['type' => 'INTEGER', 'default' => 255, 'null' => true],
            'options'    => ['type' => 'TEXT', 'null' => true],
            'reqd'       => ['type' => 'SMALLINT', 'default' => 0],
            'read_only'  => ['type' => 'SMALLINT', 'default' => 0],
            'hidden'     => ['type' => 'SMALLINT', 'default' => 0],
            'idx'        => ['type' => 'INTEGER', 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('parent', self::T_ENTITY, 'name', 'CASCADE', 'CASCADE');
        $this->forge->createTable(self::T_FIELD, true);
        
        // Thêm IF NOT EXISTS để cô lập hoàn toàn lỗi dở dang của Postgres
        $this->db->query('CREATE UNIQUE INDEX IF NOT EXISTS unique_parent_fieldname ON ' . self::T_FIELD . ' (parent, fieldname)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_field_parent ON ' . self::T_FIELD . ' (parent)');

        // ==========================================
        // 3. BẢNG: sys_entity_custom
        // ==========================================
        $this->forge->addField([
            'entity_name'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'apply_to_role' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'custom_meta'   => ['type' => 'JSONB', 'default' => '{}'],
        ]);
        $this->forge->addKey('entity_name', true);
        $this->forge->addForeignKey('entity_name', self::T_ENTITY, 'name', 'CASCADE', 'CASCADE');
        $this->forge->createTable(self::T_CUSTOM, true);

        // ==========================================
        // 4. BẢNG: sys_user
        // ==========================================
        $this->forge->addField([
            'name'                  => ['type' => 'VARCHAR', 'constraint' => 100],
            'password'              => ['type' => 'VARCHAR', 'constraint' => 255],
            'roles'                 => ['type' => 'JSONB', 'default' => '[]'],
            'user_metadata'         => ['type' => 'JSONB', 'default' => '{}'],
            'is_active'             => ['type' => 'SMALLINT', 'default' => 1],
            'api_token_hash'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'api_token_expires_at'   => ['type' => 'TIMESTAMP', 'null' => true],
            'last_login_at'         => ['type' => 'TIMESTAMP', 'null' => true],
            'failed_login_attempts'  => ['type' => 'INTEGER', 'default' => 0],
            'locked_until'          => ['type' => 'TIMESTAMP', 'null' => true],
            'created_at'            => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'            => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addKey('name', true);
        $this->forge->createTable(self::T_USER, true);

        // ==========================================
        // 5. BẢNG: sys_permission
        // ==========================================
        $this->forge->addField([
            'id'                => ['type' => 'SERIAL'],
            'role'              => ['type' => 'VARCHAR', 'constraint' => 100],
            'entity'            => ['type' => 'VARCHAR', 'constraint' => 100],
            'state'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => '*'],
            'actions'           => ['type' => 'JSONB', 'default' => '{"read": 1, "write": 0, "create": 0, "delete": 0, "submit": 0}'],
            'field_permissions' => ['type' => 'JSONB', 'default' => '{}'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('entity', self::T_ENTITY, 'name', 'CASCADE', 'CASCADE');
        $this->forge->createTable(self::T_PERMISSION, true);
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_perm_role_entity ON ' . self::T_PERMISSION . ' (role, entity)');

        // ==========================================
        // 6. BẢNG: sys_sequence
        // ==========================================
        $this->forge->addField([
            'key'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'current_value' => ['type' => 'INTEGER', 'default' => 0],
        ]);
        $this->forge->addKey('key', true);
        $this->forge->createTable(self::T_SEQUENCE, true);

        // ==========================================
        // 7. BẢNG: sys_audit_trail
        // ==========================================
        $this->forge->addField([
            'id'         => ['type' => 'BIGSERIAL'],
            'entity'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'doc_id'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'action'     => ['type' => 'VARCHAR', 'constraint' => 20],
            'changed_by' => ['type' => 'VARCHAR', 'constraint' => 100],
            'changed_at' => ['type' => 'TIMESTAMP'], 
            'delta'      => ['type' => 'JSONB', 'default' => '{}'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable(self::T_AUDIT, true);
        
        $this->db->query('ALTER TABLE ' . self::T_AUDIT . ' ALTER COLUMN changed_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_audit_doc ON ' . self::T_AUDIT . ' (entity, doc_id)');

        // ==========================================
        // 8. BẢNG: sys_queue_job
        // ==========================================
        $this->forge->addField([
            'id'         => ['type' => 'BIGSERIAL'],
            'job_type'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'payload'    => ['type' => 'JSONB', 'default' => '{}'],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'queued'],
            'attempts'   => ['type' => 'INTEGER', 'default' => 0],
            'error_log'  => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'TIMESTAMP'], 
            'updated_at' => ['type' => 'TIMESTAMP'], 
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable(self::T_QUEUE, true);
        
        $this->db->query('ALTER TABLE ' . self::T_QUEUE . ' ALTER COLUMN created_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ' . self::T_QUEUE . ' ALTER COLUMN updated_at SET DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_queue_status ON ' . self::T_QUEUE . ' (status)');
    }

    public function down()
    {
        $this->forge->dropTable(self::T_QUEUE, true);
        $this->forge->dropTable(self::T_AUDIT, true);
        $this->forge->dropTable(self::T_SEQUENCE, true);
        $this->forge->dropTable(self::T_PERMISSION, true);
        $this->forge->dropTable(self::T_USER, true);
        $this->forge->dropTable(self::T_CUSTOM, true);
        $this->forge->dropTable(self::T_FIELD, true);
        $this->forge->dropTable(self::T_ENTITY, true);
    }
}
