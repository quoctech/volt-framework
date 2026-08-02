<?php

declare(strict_types=1);

use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ReflectionHelper;
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

    private function createController(): VoltResourceController
    {
        $this->setUpControllerTestTrait();
        $this->controller(VoltResourceController::class);

        return $this->controller;
    }
}
