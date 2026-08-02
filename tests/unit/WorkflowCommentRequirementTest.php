<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Engine\WorkflowEngine;

/**
 * Integration tests cho yêu cầu comment của workflow action (reject/send_back).
 *
 * Yêu cầu: database đã migrated (volt:core-migrate) và có workflow employee_wf.
 *
 * @internal
 */
final class WorkflowCommentRequirementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private const WORKFLOW = 'comment_req_wf';

    private string $docName = '';

    protected function setUp(): void
    {
        parent::setUp();

        $db = VoltDatabase::connection();

        // Tạo workflow riêng có transition reject.
        $db->table('sys_workflow')->insert([
            'name'          => self::WORKFLOW,
            'entity'        => 'test_wf',
            'label'         => 'Comment Requirement Workflow',
            'is_active'     => 1,
            'states_order'  => json_encode(['Draft', 'Pending Approval', 'Rejected']),
            'custom_attributes' => '{}',
        ]);

        $states = [
            ['name' => 'Draft',           'idx' => 0, 'docstatus' => 0, 'allow_edit' => 1, 'is_final' => 0],
            ['name' => 'Pending Approval', 'idx' => 1, 'docstatus' => 1, 'allow_edit' => 0, 'is_final' => 0],
            ['name' => 'Rejected',        'idx' => 2, 'docstatus' => 0, 'allow_edit' => 1, 'is_final' => 0],
        ];
        foreach ($states as $state) {
            $db->table('sys_workflow_state')->insert(array_merge($state, ['workflow' => self::WORKFLOW, 'label' => $state['name'], 'color' => 'gray', 'custom_attributes' => '{}']));
        }

        $transitions = [
            ['name' => self::WORKFLOW . '.Draft.submit', 'workflow' => self::WORKFLOW, 'from_state' => 'Draft', 'to_state' => 'Pending Approval', 'action' => 'submit', 'label' => '', 'allowed_roles' => '[]', 'idx' => 0],
            ['name' => self::WORKFLOW . '.Pending Approval.reject', 'workflow' => self::WORKFLOW, 'from_state' => 'Pending Approval', 'to_state' => 'Rejected', 'action' => 'reject', 'label' => '', 'allowed_roles' => '[]', 'idx' => 1],
        ];
        foreach ($transitions as $t) {
            $db->table('sys_workflow_transition')->insert(array_merge($t, ['required_condition' => '', 'custom_attributes' => '{}']));
        }

        // Bảng vật lý + document ở trạng thái Pending Approval.
        $db->query("DROP TABLE IF EXISTS tab_test_wf");
        $db->query("
            CREATE TABLE tab_test_wf (
                name VARCHAR(100) PRIMARY KEY,
                docstatus SMALLINT DEFAULT 0,
                workflow_state VARCHAR(100) DEFAULT 'Draft',
                owner VARCHAR(100) DEFAULT 'test',
                amended_from VARCHAR(100) DEFAULT NULL,
                creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->docName = 'REQ-00001';
        $db->table('tab_test_wf')->insert([
            'name'           => $this->docName,
            'docstatus'      => 1,
            'workflow_state' => 'Pending Approval',
            'owner'          => 'test',
        ]);
    }

    protected function tearDown(): void
    {
        $db = VoltDatabase::connection();
        $db->query("DROP TABLE IF EXISTS tab_test_wf");
        $db->table('sys_workflow_transition')->where('workflow', self::WORKFLOW)->delete();
        $db->table('sys_workflow_state')->where('workflow', self::WORKFLOW)->delete();
        $db->table('sys_workflow')->where('name', self::WORKFLOW)->delete();

        parent::tearDown();
    }

    public function testRejectWithoutCommentThrows(): void
    {
        $engine = new WorkflowEngine();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Action 'reject' requires a comment.");

        $engine->applyTransition('test_wf', $this->docName, 'reject');
    }

    public function testRejectWithCommentSucceeds(): void
    {
        $engine = new WorkflowEngine();

        $result = $engine->applyTransition('test_wf', $this->docName, 'reject', '  Lý do từ chối  ');

        $this->assertSame('Rejected', $result['new_state']);
        $this->assertSame(0, $result['docstatus']);

        $db = VoltDatabase::connection();
        $row = $db->table('tab_test_wf')->where('name', $this->docName)->get()->getRowArray();
        $this->assertSame('Rejected', $row['workflow_state']);
    }

    public function testSubmitWithoutCommentSucceeds(): void
    {
        // submit không yêu cầu comment -> vẫn chạy bình thường.
        $db = VoltDatabase::connection();
        $db->table('tab_test_wf')->where('name', $this->docName)->update([
            'workflow_state' => 'Draft',
            'docstatus'      => 0,
        ]);

        $engine = new WorkflowEngine();
        $result = $engine->applyTransition('test_wf', $this->docName, 'submit');

        $this->assertSame('Pending Approval', $result['new_state']);
        $this->assertSame(1, $result['docstatus']);
    }
}
