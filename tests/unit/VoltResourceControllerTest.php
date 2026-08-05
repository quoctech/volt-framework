<?php

declare(strict_types=1);

use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ReflectionHelper;
use Config\Services;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Auth\Services\AuthService;
use Volt\Core\Audit\AuditTrailWriter;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Metadata\Controllers\VoltResourceController;

/**
 * Integration tests cho fallback view của VoltResourceController.
 *
 * Entity test_evt tồn tại trong DB (module=core) nhưng module core không có
 * view scaffold, nên indexView/createView/editView phải rơi về template generic.
 *
 * @internal
 */
final class VoltResourceControllerTest extends CIUnitTestCase
{
    use ControllerTestTrait;
    use ReflectionHelper;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the fallback-entity fixture exists regardless of test order
        // (VoltModelEventTest deletes its sys_entity row in tearDown).
        $db = VoltDatabase::connection();
        $exists = $db->table('sys_entity')->where('name', 'test_evt')->get()->getRowArray();
        if (! is_array($exists)) {
            $db->table('sys_entity')->insert([
                'name'              => 'test_evt',
                'module'            => 'core',
                'autoname'          => 'HASH',
                'issingle'          => 0,
                'istable'           => 0,
                'custom_attributes' => json_encode(['is_submittable' => true, 'label' => 'Test Evt']),
            ]);
        }
    }

    protected function tearDown(): void
    {
        VoltDatabase::connection()->table('sys_entity')->where('name', 'test_evt')->delete();
        parent::tearDown();
    }

    public function testRenderListViewFallsBackToGenericTemplate(): void
    {
        $controller = $this->createController();

        $render = self::getPrivateMethodInvoker($controller, 'renderListView');
        $html = $render('TestEvt', 'Core', 'test_evt', 'core', ['entity' => [], 'workflow' => []]);

        $this->assertIsString($html);
        $this->assertStringContainsString('TestEvt List', $html);
        $this->assertStringContainsString('VoltListApp', $html);
    }

    public function testRenderListViewFallsBackWithColumns(): void
    {
        $controller = $this->createController();

        $render = self::getPrivateMethodInvoker($controller, 'renderListView');
        $html = $render('Employee', 'Hrms', 'employee', 'hrms', ['entity' => [], 'workflow' => []]);

        // Hrms module DOES have employee_list.php -> should render module view, no exception.
        $this->assertIsString($html);
        $this->assertStringContainsString('Employee List', $html);
    }

    public function testRenderFormViewFallsBackToGenericTemplate(): void
    {
        $controller = $this->createController();

        $render = self::getPrivateMethodInvoker($controller, 'renderFormView');
        $html = $render('TestEvt', 'Core', 'test_evt', 'core', ['entity' => [], 'workflow' => []], 'Test Evt', '');

        $this->assertIsString($html);
        $this->assertStringContainsString('Test Evt', $html);
        $this->assertStringContainsString('VoltFormApp', $html);
    }

    public function testListColumnsSkipsChildTableFields(): void
    {
        $controller = $this->createController();

        $columns = self::getPrivateMethodInvoker($controller, 'listColumns');
        $result = $columns('Employee');

        $this->assertIsArray($result);
        foreach ($result as $column) {
            $this->assertNotTrue($column['fieldtype'] === 'Table');
        }
    }

    public function testDecodeDeltaParsesJsonPayload(): void
    {
        $controller = $this->createController();
        $decode = self::getPrivateMethodInvoker($controller, 'decodeDelta');

        // JSON string → array
        $decoded = $decode('{"before":{"workflow_state":"Draft"},"after":{"workflow_state":"Submitted"}}');
        $this->assertIsArray($decoded);
        $this->assertSame('Submitted', $decoded['after']['workflow_state']);

        // Empty / null → []
        $this->assertSame([], $decode(null));
        $this->assertSame([], $decode(''));
        $this->assertSame([], $decode('not-json'));

        // Already array → passthrough
        $this->assertSame(['a' => 1], $decode(['a' => 1]));
    }

    public function testActivityEndpointReturnsAuditHistoryForDoc(): void
    {
        $db = VoltDatabase::connection();
        $docId = 'ACT-TEST-' . strtoupper(uniqid());

        // Ghi 2 dòng audit cho record này bằng writer (hub/connection = default).
        // Entity ghi dạng title-case (giống model thật), endpoint query lowercase —
        // phải khớp case-insensitive.
        $writer = new AuditTrailWriter($db);
        $writer->write(AuditTrailWriter::CAT_DATA, 'create', 'Employee', $docId, [], ['name' => $docId], 'tester');
        $writer->write(AuditTrailWriter::CAT_WORKFLOW, 'workflow:submit', 'Employee', $docId, ['workflow_state' => 'Draft'], ['workflow_state' => 'Submitted'], 'tester');

        // Mock voltAuth trả về admin để canRead() true.
        $admin = (new UserEntity())->fill(['name' => 'admin', 'roles' => ['admin']]);
        $mockAuth = $this->createMock(AuthService::class);
        $mockAuth->method('currentUser')->willReturn($admin);
        Services::injectMock('voltAuth', $mockAuth);

        $controller = $this->createController();
        $response = $controller->activity('employee', $docId);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);

        $this->assertSame('ok', $body['status']);
        $this->assertSame('employee', $body['entity']);
        $this->assertSame($docId, $body['doc_id']);
        $this->assertCount(2, $body['items']);

        $actions = array_column($body['items'], 'action');
        sort($actions);
        $this->assertSame(['create', 'workflow:submit'], $actions);

        $submitItem = array_find($body['items'], static fn (array $i): bool => $i['action'] === 'workflow:submit');
        $this->assertIsArray($submitItem);
        $this->assertSame('Submitted', $submitItem['delta']['after']['workflow_state']);
        $this->assertSame('tester', $submitItem['changed_by']);
    }

    public function testEditViewIncludesActivitySectionAndUrl(): void
    {
        $admin = (new UserEntity())->fill(['name' => 'admin', 'roles' => ['admin']]);
        $mockAuth = $this->createMock(AuthService::class);
        $mockAuth->method('currentUser')->willReturn($admin);
        Services::injectMock('voltAuth', $mockAuth);

        $controller = $this->createController();
        $output = $controller->editView('employee', 'E-2026-00024');

        $this->assertIsString($output);
        $this->assertStringContainsString('activityUrlBase', $output);
        $this->assertStringContainsString('hrms', $output);
        $this->assertStringContainsString('api', $output);
        $this->assertStringContainsString('employee', $output);
        $this->assertStringContainsString('activity', $output);
        $this->assertStringContainsString('loadActivity', $output);
        $this->assertStringContainsString('Activity', $output);
    }

    private function createController(): VoltResourceController
    {
        $this->setUpControllerTestTrait();
        $this->controller(VoltResourceController::class);

        return $this->controller;
    }
}
