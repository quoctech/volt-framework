<?php

declare(strict_types=1);

use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;

/**
 * @internal
 */
final class RestApiTest extends \CodeIgniter\Test\CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSysTables();
        $this->mockServices();

        $this->cleanupTestRecords();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestRecords();
    }

    private function cleanupTestRecords(): void
    {
        $db = \Config\Database::connect();
        $db->table('tab_employee_checkin')->where('owner', 'testuser')->delete();
        $db->table('tab_employee_skill_map')->where('owner', 'testuser')->delete();
        $db->table('tab_traning_event')->where('owner', 'testuser')->delete();
        $db->table('tab_employee')->where('owner', 'testuser')->delete();
    }

    private function ensureSysTables(): void
    {
        $db = \Config\Database::connect();

        $db->query('CREATE TABLE IF NOT EXISTS sys_entity (
            name VARCHAR(100) PRIMARY KEY,
            module VARCHAR(50),
            issingle SMALLINT DEFAULT 0,
            istable SMALLINT DEFAULT 0,
            autoname VARCHAR(100),
            states JSONB DEFAULT \'{}\',
            custom_attributes JSONB DEFAULT \'{}\'
        )');
        $db->query('CREATE TABLE IF NOT EXISTS sys_entity_field (
            id SERIAL,
            parent VARCHAR(100) REFERENCES sys_entity(name),
            fieldname VARCHAR(100),
            label VARCHAR(255),
            fieldtype VARCHAR(50),
            length INTEGER,
            options TEXT,
            reqd SMALLINT DEFAULT 0,
            read_only SMALLINT DEFAULT 0,
            hidden SMALLINT DEFAULT 0,
            idx INTEGER DEFAULT 0
        )');
        $db->query('CREATE UNIQUE INDEX IF NOT EXISTS uk_sys_field_parent_fn ON sys_entity_field (parent, fieldname)');
        $db->query('CREATE TABLE IF NOT EXISTS sys_entity_custom (
            entity_name VARCHAR(100) PRIMARY KEY REFERENCES sys_entity(name) ON DELETE CASCADE ON UPDATE CASCADE,
            apply_to_role VARCHAR(100),
            custom_meta JSONB DEFAULT \'{}\'
        )');
        $db->query('CREATE TABLE IF NOT EXISTS sys_user (
            name VARCHAR(100) PRIMARY KEY,
            password VARCHAR(255),
            roles JSONB DEFAULT \'[]\',
            user_metadata JSONB DEFAULT \'{}\',
            is_active SMALLINT DEFAULT 1,
            api_token_hash VARCHAR(255),
            api_token_expires_at TIMESTAMP,
            last_login_at TIMESTAMP,
            failed_login_attempts INTEGER DEFAULT 0,
            locked_until TIMESTAMP,
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');
        $db->query('CREATE TABLE IF NOT EXISTS sys_permission (
            id SERIAL PRIMARY KEY,
            role VARCHAR(100),
            entity VARCHAR(100) REFERENCES sys_entity(name),
            state VARCHAR(50) DEFAULT \'*\',
            actions JSONB DEFAULT \'{"read":1,"write":0,"create":0,"delete":0,"submit":0}\',
            field_permissions JSONB DEFAULT \'{}\'
        )');
        $db->query('CREATE INDEX IF NOT EXISTS idx_sys_perm_role_entity ON sys_permission (role, entity)');
        $db->query('CREATE TABLE IF NOT EXISTS sys_sequence (
            key VARCHAR(150) PRIMARY KEY,
            current_value INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE IF NOT EXISTS sys_audit_trail (
            id BIGSERIAL PRIMARY KEY,
            entity VARCHAR(100),
            doc_id VARCHAR(100),
            action VARCHAR(20),
            changed_by VARCHAR(100),
            changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            delta JSONB DEFAULT \'{}\'
        )');
        $db->query('CREATE TABLE IF NOT EXISTS sys_queue_job (
            id BIGSERIAL PRIMARY KEY,
            job_type VARCHAR(100),
            payload JSONB DEFAULT \'{}\',
            status VARCHAR(20) DEFAULT \'queued\',
            attempts INTEGER DEFAULT 0,
            error_log TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $db->query('CREATE TABLE IF NOT EXISTS sys_role (
            name VARCHAR(100) PRIMARY KEY,
            label VARCHAR(255),
            description TEXT,
            is_system SMALLINT DEFAULT 0,
            owner VARCHAR(100),
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');
        $db->query('CREATE TABLE IF NOT EXISTS sys_note (
            id BIGSERIAL PRIMARY KEY,
            title VARCHAR(255),
            body TEXT,
            status VARCHAR(20) DEFAULT \'draft\',
            owner VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $db->query('CREATE INDEX IF NOT EXISTS idx_sys_note_status ON sys_note (status)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_sys_note_owner ON sys_note (owner)');
        $db->query('CREATE TABLE IF NOT EXISTS sys_awesome_bar (
            id SERIAL PRIMARY KEY,
            item_type VARCHAR(50),
            item_name VARCHAR(100),
            label VARCHAR(255),
            description TEXT,
            route VARCHAR(255),
            module VARCHAR(100),
            is_core SMALLINT DEFAULT 0,
            owner VARCHAR(100),
            created_at TIMESTAMP,
            updated_at TIMESTAMP
        )');
        $db->query('CREATE INDEX IF NOT EXISTS idx_awesome_item_type ON sys_awesome_bar (item_type)');
        $db->query('CREATE INDEX IF NOT EXISTS idx_awesome_item_name ON sys_awesome_bar (item_name)');
    }

    private function mockServices(): void
    {
        $mockAuth = $this->createMock(AuthService::class);

        $adminUser = new UserEntity([
            'name'             => 'admin',
            'password'         => '',
            'roles'            => ['admin'],
            'user_metadata'    => ['bootstrap_admin' => true],
            'is_active'        => 1,
            'failed_login_attempts' => 0,
        ]);

        $mockAuth->method('currentUser')->willReturn($adminUser);

        Services::injectMock('voltAuth', $mockAuth);
    }

    // ---- tests ----

    public function testIndexReturns200(): void
    {
        $result = $this->call('GET', 'hrms/rest/employee');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('data', $body);
    }

    public function testIndexWithSearch(): void
    {
        $result = $this->call('GET', 'hrms/rest/employee', ['q' => 'john']);

        $result->assertStatus(200);
    }

    public function testShowReturns200(): void
    {
        $db = \Config\Database::connect();
        $name = 'test-employee-show-' . uniqid();
        $db->table('tab_employee')->insert([
            'name'           => $name,
            'owner'          => 'testuser',
            'employee_name'  => 'Show Test',
            'employee_age'   => 30,
        ]);

        $result = $this->call('GET', "hrms/rest/employee/{$name}");

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $body);
    }

    public function testShowReturns404(): void
    {
        $result = $this->call('GET', 'hrms/rest/employee/nonexistent-id');

        $result->assertStatus(404);
    }

    public function testStoreReturns201(): void
    {
        $uniqueName = 'test-api-store-' . uniqid();
        $payload = [
            'name'          => $uniqueName,
            'employee_name' => 'API Store',
            'employee_age'  => 25,
            'owner'         => 'testuser',
        ];

        $result = $this->withBodyFormat('json')->call('POST', 'hrms/rest/employee', $payload);

        $result->assertStatus(201);
        $body = json_decode($result->getJSON(), true);
        $this->assertArrayHasKey('data', $body);
    }

    public function testStoreReturns400ForInvalidJson(): void
    {
        $result = $this->withBody('not-json-at-all')
            ->withBodyFormat('json')
            ->call('POST', 'hrms/rest/employee');

        $result->assertStatus(400);
    }

    public function testUpdateReturns200(): void
    {
        $db = \Config\Database::connect();
        $uniqueName = 'test-api-upd-' . uniqid();
        $db->table('tab_employee')->insert([
            'name'          => $uniqueName,
            'owner'         => 'testuser',
            'employee_name' => 'Before',
        ]);

        $result = $this->withBodyFormat('json')->call('PUT', "hrms/rest/employee/{$uniqueName}", [
            'employee_name' => 'After',
        ]);

        $result->assertStatus(200);
    }

    public function testUpdateReturns404(): void
    {
        $result = $this->withBodyFormat('json')->call('PUT', 'hrms/rest/employee/nonexistent-id', [
            'employee_name' => 'Nope',
        ]);

        $result->assertStatus(404);
    }

    public function testDestroyReturns200(): void
    {
        $db = \Config\Database::connect();
        $uniqueName = 'test-api-del-' . uniqid();
        $db->table('tab_employee')->insert([
            'name'          => $uniqueName,
            'owner'         => 'testuser',
            'employee_name' => 'Delete Me',
        ]);

        $result = $this->call('DELETE', "hrms/rest/employee/{$uniqueName}");

        $result->assertStatus(200);
    }

    public function testDestroyReturns404(): void
    {
        $result = $this->call('DELETE', 'hrms/rest/employee/nonexistent-id');

        $result->assertStatus(404);
    }
}
