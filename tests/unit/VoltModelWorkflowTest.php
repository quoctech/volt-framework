<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Database\VoltDatabase;

/**
 * Integration tests cho VoltModel workflow methods.
 *
 * Yêu cầu: database đã migrated (volt:core-migrate) và có entity Leave.
 *
 * @internal
 */
final class VoltModelWorkflowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private UserEntity $testActor;

    private string $testDocName = '';

    protected function setUp(): void
    {
        parent::setUp();

        $db = VoltDatabase::connection();

        // Create an admin actor to bypass permission checks
        $this->testActor = new UserEntity();
        $this->testActor->name = 'admin';
        $this->testActor->roles = ['admin'];

        // Seed a minimal sys_entity for testing nếu chưa có
        $exists = $db->table('sys_entity')->where('name', 'test_wf')->get()->getRowArray();
        if (! is_array($exists)) {
            $db->table('sys_entity')->insert([
                'name'              => 'test_wf',
                'module'            => 'core',
                'autoname'          => 'HASH',
                'issingle'          => 0,
                'istable'           => 0,
                'custom_attributes' => json_encode(['is_submittable' => true, 'label' => 'Test WF']),
            ]);
        }

        // Drop and recreate test table
        $tableName = 'tab_test_wf';
        $db->query("DROP TABLE IF EXISTS {$tableName}");

        $db->query("
            CREATE TABLE {$tableName} (
                name VARCHAR(100) PRIMARY KEY,
                docstatus SMALLINT DEFAULT 0,
                workflow_state VARCHAR(100) DEFAULT 'Draft',
                owner VARCHAR(100) DEFAULT 'test',
                amended_from VARCHAR(100) DEFAULT NULL,
                creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Insert a test document in Draft state
        $this->testDocName = 'WF-TEST-00001';
        $db->table($tableName)->insert([
            'name'           => $this->testDocName,
            'docstatus'      => 0,
            'workflow_state' => 'Draft',
            'owner'          => 'test',
        ]);
    }

    protected function tearDown(): void
    {
        $db = VoltDatabase::connection();
        $db->query("DROP TABLE IF EXISTS tab_test_wf");

        parent::tearDown();
    }

    public function testSubmitDraft(): void
    {
        $model = $this->createModel();
        $result = $model->submit($this->testDocName);

        $this->assertSame('Submitted', $result['new_state']);
        $this->assertSame(1, $result['docstatus']);
        $this->assertSame('submit', $result['action']);
    }

    public function testApproveSubmitted(): void
    {
        $model = $this->createModel();

        $model->submit($this->testDocName);
        $result = $model->approve($this->testDocName);

        $this->assertSame('Approved', $result['new_state']);
        $this->assertSame(1, $result['docstatus']);
        $this->assertSame('approve', $result['action']);
    }

    public function testCancelSubmitted(): void
    {
        $model = $this->createModel();

        $model->submit($this->testDocName);
        $result = $model->cancel($this->testDocName);

        $this->assertSame('Cancelled', $result['new_state']);
        $this->assertSame(2, $result['docstatus']);
        $this->assertSame('cancel', $result['action']);
    }

    public function testAmendCancelled(): void
    {
        $model = $this->createModel();

        $model->submit($this->testDocName);
        $model->cancel($this->testDocName);
        $amended = $model->amend($this->testDocName);

        $this->assertIsArray($amended);
        $this->assertSame('Draft', $amended['workflow_state']);
        $this->assertSame(0, (int) $amended['docstatus']);

        // Verify old doc has amended_from
        $db = VoltDatabase::connection();
        $old = $db->table('tab_test_wf')->where('name', $this->testDocName)->get()->getRowArray();
        $this->assertIsArray($old);
        $this->assertSame($amended['name'], $old['amended_from']);
    }

    public function testSubmitInvalidTransitionFromApproved(): void
    {
        $model = $this->createModel();

        $model->submit($this->testDocName);
        $model->approve($this->testDocName);

        $this->expectException(\RuntimeException::class);
        $model->submit($this->testDocName);
    }

    public function testCancelInvalidTransitionFromDraft(): void
    {
        $model = $this->createModel();

        $this->expectException(\RuntimeException::class);
        $model->cancel($this->testDocName);
    }

    public function testAmendInvalidTransitionFromDraft(): void
    {
        $model = $this->createModel();

        $this->expectException(\RuntimeException::class);
        $model->amend($this->testDocName);
    }

    public function testEditSubmittedDocumentThrows(): void
    {
        $model = $this->createModel();
        $model->submit($this->testDocName);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cannot be edited');
        $model->update($this->testDocName, ['owner' => 'someone_else']);
    }

    private function createModel(): Volt\Core\Models\VoltModel
    {
        $model = new class extends Volt\Core\Models\VoltModel {
            protected $table = 'tab_test_wf';
            protected $primaryKey = 'name';
            protected $returnType = 'array';
            protected $useAutoIncrement = false;
            protected $protectFields = false;
            protected $allowedFields = [];

            public function __construct()
            {
                parent::__construct();
                $this->setEntityName('test_wf');
            }
        };

        $model->setActor($this->testActor);

        return $model;
    }
}
