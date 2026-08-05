<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Database\VoltDatabase;

/**
 * Integration tests cho audit trail ghi trong workflow transitions.
 *
 * Kiểm tra sys_audit_trail được ghi đúng khi có comment.
 *
 * @internal
 */
final class WorkflowAuditTrailTest extends CIUnitTestCase
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

        $this->testActor = new UserEntity();
        $this->testActor->name = 'admin';
        $this->testActor->roles = ['admin'];

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
                modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP DEFAULT NULL
            )
        ");

        $this->testDocName = 'AT-TEST-' . strtoupper(uniqid());
        $db->table($tableName)->insert([
            'name'           => $this->testDocName,
            'docstatus'      => 0,
            'workflow_state' => 'Draft',
            'owner'          => 'test',
        ]);

        // sys_audit_trail là append-only nên không thể delete dọn dẹp theo doc_id.
        // Mỗi test dùng doc name duy nhất để tránh nhiễu giữa các lần chạy.
    }

    protected function tearDown(): void
    {
        $db = VoltDatabase::connection();
        $db->query('DROP TABLE IF EXISTS tab_test_wf');
        $db->table('sys_entity')->where('name', 'test_wf')->delete();
        parent::tearDown();
    }

    public function testSubmitCreatesAuditTrailWithComment(): void
    {
        $model = $this->createModel();

        $model->submit($this->testDocName, 'Submitting for approval');

        $trail = $this->getAuditTrail('workflow:submit');
        $this->assertNotNull($trail, 'Expected audit trail for submit');
        $this->assertSame($this->testDocName, $trail['doc_id']);
        $this->assertSame('test_wf', $trail['entity']);
        // In test context, voltAuth returns null → WorkflowEngine uses 'system'
        $this->assertSame('system', $trail['changed_by']);

        $delta = json_decode($trail['delta'], true);
        $this->assertIsArray($delta);
        $this->assertSame('Draft', $delta['before']['workflow_state']);
        $this->assertSame('Submitted', $delta['after']['workflow_state']);
        $this->assertSame('Submitting for approval', $delta['after']['comment']);
    }

    public function testApproveCreatesAuditTrailWithComment(): void
    {
        $model = $this->createModel();
        $model->submit($this->testDocName, 'Submitting');

        $model->approve($this->testDocName, 'Approved, looks good');

        $trail = $this->getAuditTrail('workflow:approve');
        $this->assertNotNull($trail, 'Expected audit trail for approve');
        // In test context, voltAuth returns null → WorkflowEngine uses 'system'
        $this->assertSame('system', $trail['changed_by']);

        $delta = json_decode($trail['delta'], true);
        $this->assertSame('Submitted', $delta['before']['workflow_state']);
        $this->assertSame('Approved', $delta['after']['workflow_state']);
        $this->assertSame('Approved, looks good', $delta['after']['comment']);
    }

    public function testCancelCreatesAuditTrailWithComment(): void
    {
        $model = $this->createModel();
        $model->submit($this->testDocName, 'Submitting');

        $model->cancel($this->testDocName, 'Cancelling due to policy');

        $trail = $this->getAuditTrail('workflow:cancel');
        $this->assertNotNull($trail, 'Expected audit trail for cancel');
        // In test context, voltAuth returns null → WorkflowEngine uses 'system'
        $this->assertSame('system', $trail['changed_by']);

        $delta = json_decode($trail['delta'], true);
        $this->assertSame('Submitted', $delta['before']['workflow_state']);
        $this->assertSame('Cancelled', $delta['after']['workflow_state']);
        $this->assertSame('Cancelling due to policy', $delta['after']['comment']);
    }

    public function testAmendCreatesAuditTrail(): void
    {
        $model = $this->createModel();
        $model->submit($this->testDocName, 'Submitting');
        $model->cancel($this->testDocName, 'Cancelling');

        $amended = $model->amend($this->testDocName, 'Amending to fix data');

        $trail = $this->getAuditTrail('workflow:amend');
        $this->assertNotNull($trail, 'Expected audit trail for amend');

        $delta = json_decode($trail['delta'], true);
        $this->assertSame('Cancelled', $delta['before']['workflow_state']);
        $this->assertSame('Draft', $delta['after']['workflow_state']);
        $this->assertSame('Amending to fix data', $delta['after']['comment']);
    }

    public function testSubmitWithoutCommentStillCreatesAuditTrail(): void
    {
        $model = $this->createModel();
        $model->submit($this->testDocName);

        $trail = $this->getAuditTrail('workflow:submit');
        $this->assertNotNull($trail, 'Audit trail must be created even without a comment');

        // Mọi transition đều được audit; comment chỉ xuất hiện khi có.
        $delta = json_decode($trail['delta'], true);
        $this->assertIsArray($delta);
        $this->assertArrayHasKey('workflow_state', $delta['after']);
        $this->assertArrayNotHasKey('comment', $delta['after']);
    }

    public function testAuditTrailHasCorrectDeltaFormat(): void
    {
        $model = $this->createModel();
        $model->submit($this->testDocName, 'Check delta format');

        $trail = $this->getAuditTrail('workflow:submit');

        $delta = json_decode($trail['delta'], true);
        $this->assertArrayHasKey('before', $delta);
        $this->assertArrayHasKey('after', $delta);
        $this->assertArrayHasKey('workflow_state', $delta['before']);
        $this->assertArrayHasKey('workflow_state', $delta['after']);
        $this->assertArrayHasKey('comment', $delta['after']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getAuditTrail(string $action): ?array
    {
        $row = VoltDatabase::connection()
            ->table('sys_audit_trail')
            ->where('doc_id', $this->testDocName)
            ->where('action', $action)
            ->orderBy('changed_at', 'DESC')
            ->get()
            ->getRowArray();

        return is_array($row) ? $row : null;
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
