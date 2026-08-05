<?php

declare(strict_types=1);

namespace Volt\Core\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Nâng cấp sys_audit_trail lên chuẩn vận hành (Frappe-style activity log):
 *
 * - widening action, thêm category/operation/status/tenant/ip/user_agent/request_id
 * - hash-chain (prev_hash + hash) để phát hiện giả mạo
 * - append-only: trigger chặn UPDATE/DELETE trực tiếp
 * - volt_audit_purge(): hàm SECURITY DEFINER duy nhất được phép xóa (retention)
 * - sys_audit_chain: bảng singleton giữ liên kết chuỗi hash
 * - sys_error_log.request_id: nối với CorrelationFilter
 */
class UpgradeAuditTrailForOps extends Migration
{
    private const T_AUDIT = 'sys_audit_trail';
    private const T_CHAIN = 'sys_audit_chain';
    private const T_ERROR = 'sys_error_log';

    private const GENESIS_HASH = 'e78a2c1b89698ef13a10e82faa0ff73f08f025499aa81922d44222d3fbce5b59';

    public function up(): void
    {
        $this->upgradeAuditTrailTable();
        $this->createChainTable();
        $this->installFunctionsAndTriggers();
        $this->upgradeErrorLogTable();
    }

    public function down(): void
    {
        $this->db->query('DROP TRIGGER IF EXISTS volt_audit_guard ON ' . self::T_AUDIT);
        $this->db->query('DROP TRIGGER IF EXISTS volt_audit_hash_set ON ' . self::T_AUDIT);
        $this->db->query('DROP TRIGGER IF EXISTS volt_audit_chain_update ON ' . self::T_AUDIT);
        $this->db->query('DROP FUNCTION IF EXISTS volt_audit_purge(integer)');
        $this->db->query('DROP FUNCTION IF EXISTS volt_audit_guard()');
        $this->db->query('DROP FUNCTION IF EXISTS volt_audit_hash_set()');
        $this->db->query('DROP FUNCTION IF EXISTS volt_audit_chain_update()');
        $this->db->query('DROP FUNCTION IF EXISTS volt_audit_hash(text, text, text, text, text, text, text, text, timestamp, text, text, text, text, text)');

        if ($this->db->tableExists(self::T_CHAIN)) {
            $this->forge->dropTable(self::T_CHAIN, true);
        }

        foreach (['request_id', 'prev_hash', 'hash', 'user_agent', 'ip_address', 'tenant', 'status', 'operation', 'category'] as $column) {
            if ($this->db->fieldExists($column, self::T_AUDIT)) {
                $this->forge->dropColumn(self::T_AUDIT, $column);
            }
        }

        if ($this->db->fieldExists('request_id', self::T_ERROR)) {
            $this->forge->dropColumn(self::T_ERROR, 'request_id');
        }
    }

    private function upgradeAuditTrailTable(): void
    {
        $this->db->query('ALTER TABLE ' . self::T_AUDIT . ' ALTER COLUMN action TYPE VARCHAR(64)');

        foreach (['entity', 'doc_id'] as $column) {
            $this->db->query('ALTER TABLE ' . self::T_AUDIT . ' ALTER COLUMN ' . $column . ' DROP NOT NULL');
        }

        $add = [];

        if (! $this->db->fieldExists('category', self::T_AUDIT)) {
            $add['category'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN category VARCHAR(30) NOT NULL DEFAULT \'data\'';
        }

        if (! $this->db->fieldExists('operation', self::T_AUDIT)) {
            $add['operation'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN operation VARCHAR(30)';
        }

        if (! $this->db->fieldExists('status', self::T_AUDIT)) {
            $add['status'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN status VARCHAR(20)';
        }

        if (! $this->db->fieldExists('tenant', self::T_AUDIT)) {
            $add['tenant'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN tenant VARCHAR(100)';
        }

        if (! $this->db->fieldExists('ip_address', self::T_AUDIT)) {
            $add['ip_address'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN ip_address VARCHAR(45)';
        }

        if (! $this->db->fieldExists('user_agent', self::T_AUDIT)) {
            $add['user_agent'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN user_agent VARCHAR(255)';
        }

        if (! $this->db->fieldExists('request_id', self::T_AUDIT)) {
            $add['request_id'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN request_id VARCHAR(64)';
        }

        if (! $this->db->fieldExists('prev_hash', self::T_AUDIT)) {
            $add['prev_hash'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN prev_hash CHAR(64)';
        }

        if (! $this->db->fieldExists('hash', self::T_AUDIT)) {
            $add['hash'] = 'ALTER TABLE ' . self::T_AUDIT . ' ADD COLUMN hash CHAR(64)';
        }

        foreach ($add as $sql) {
            $this->db->query($sql);
        }

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_audit_category ON ' . self::T_AUDIT . ' (category, changed_at)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_audit_request ON ' . self::T_AUDIT . ' (request_id)');
        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_audit_tenant ON ' . self::T_AUDIT . ' (tenant)');
    }

    private function createChainTable(): void
    {
        if ($this->db->tableExists(self::T_CHAIN)) {
            return;
        }

        $this->db->query('CREATE TABLE ' . self::T_CHAIN . ' (
            lock_key INTEGER PRIMARY KEY,
            last_hash CHAR(64) NOT NULL,
            last_id BIGINT NOT NULL DEFAULT 0
        )');

        $this->db->query('INSERT INTO ' . self::T_CHAIN . ' (lock_key, last_hash, last_id) VALUES (1, \'' . self::GENESIS_HASH . '\', 0)');
    }

    private function installFunctionsAndTriggers(): void
    {
        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION volt_audit_hash(
                p_prev_hash text, p_category text, p_entity text, p_doc_id text, p_action text,
                p_operation text, p_status text, p_changed_by text, p_changed_at timestamp,
                p_tenant text, p_ip_address text, p_user_agent text, p_request_id text, p_delta text
            ) RETURNS text LANGUAGE sql IMMUTABLE AS $f$
                SELECT encode(sha256(convert_to(concat_ws('|',
                    COALESCE(p_prev_hash, ''),
                    COALESCE(p_category, ''),
                    COALESCE(p_entity, ''),
                    COALESCE(p_doc_id, ''),
                    COALESCE(p_action, ''),
                    COALESCE(p_operation, ''),
                    COALESCE(p_status, ''),
                    COALESCE(p_changed_by, ''),
                    to_char(p_changed_at, 'YYYY-MM-DD HH24:MI:SS.US'),
                    COALESCE(p_tenant, ''),
                    COALESCE(p_ip_address, ''),
                    COALESCE(p_user_agent, ''),
                    COALESCE(p_request_id, ''),
                    COALESCE(p_delta, '{}')
                ), 'UTF8')), 'hex')
            $f$;
        SQL);

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION volt_audit_hash_set() RETURNS trigger LANGUAGE plpgsql AS $f$
            BEGIN
                IF NEW.prev_hash IS NULL THEN
                    SELECT last_hash INTO NEW.prev_hash
                    FROM sys_audit_chain
                    WHERE lock_key = 1;
                END IF;

                NEW.hash := volt_audit_hash(
                    NEW.prev_hash,
                    NEW.category,
                    NEW.entity,
                    NEW.doc_id,
                    NEW.action,
                    NEW.operation,
                    NEW.status,
                    NEW.changed_by,
                    NEW.changed_at,
                    NEW.tenant,
                    NEW.ip_address,
                    NEW.user_agent,
                    NEW.request_id,
                    NEW.delta::text
                );

                RETURN NEW;
            END
            $f$;
        SQL);

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION volt_audit_chain_update() RETURNS trigger LANGUAGE plpgsql AS $f$
            BEGIN
                UPDATE sys_audit_chain
                SET last_hash = NEW.hash, last_id = NEW.id
                WHERE lock_key = 1;
                RETURN NEW;
            END
            $f$;
        SQL);

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION volt_audit_guard() RETURNS trigger LANGUAGE plpgsql AS $f$
            BEGIN
                IF TG_OP = 'DELETE' AND COALESCE(current_setting('volt.purge', true), '0') = '1' THEN
                    RETURN OLD;
                END IF;

                RAISE EXCEPTION 'sys_audit_trail is append-only; % is not permitted', TG_OP;
            END
            $f$;
        SQL);

        $this->db->query(<<<'SQL'
            CREATE OR REPLACE FUNCTION volt_audit_purge(p_days integer DEFAULT 730)
            RETURNS integer LANGUAGE plpgsql SECURITY DEFINER AS $f$
            DECLARE
                v_count integer;
            BEGIN
                PERFORM set_config('volt.purge', '1', true);

                DELETE FROM sys_audit_trail
                WHERE changed_at < (CURRENT_TIMESTAMP - make_interval(days => p_days));

                GET DIAGNOSTICS v_count = ROW_COUNT;
                RETURN v_count;
            END
            $f$;
        SQL);

        $this->db->query('DROP TRIGGER IF EXISTS volt_audit_hash_set ON ' . self::T_AUDIT);
        $this->db->query('CREATE TRIGGER volt_audit_hash_set
            BEFORE INSERT ON ' . self::T_AUDIT . '
            FOR EACH ROW EXECUTE FUNCTION volt_audit_hash_set()');

        $this->db->query('DROP TRIGGER IF EXISTS volt_audit_chain_update ON ' . self::T_AUDIT);
        $this->db->query('CREATE TRIGGER volt_audit_chain_update
            AFTER INSERT ON ' . self::T_AUDIT . '
            FOR EACH ROW EXECUTE FUNCTION volt_audit_chain_update()');

        $this->db->query('DROP TRIGGER IF EXISTS volt_audit_guard ON ' . self::T_AUDIT);
        $this->db->query('CREATE TRIGGER volt_audit_guard
            BEFORE UPDATE OR DELETE ON ' . self::T_AUDIT . '
            FOR EACH ROW EXECUTE FUNCTION volt_audit_guard()');
    }

    private function upgradeErrorLogTable(): void
    {
        if (! $this->db->fieldExists('request_id', self::T_ERROR)) {
            $this->forge->addColumn(self::T_ERROR, [
                'request_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            ]);
        }

        $this->db->query('CREATE INDEX IF NOT EXISTS idx_sys_error_log_request ON ' . self::T_ERROR . ' (request_id)');
    }
}
