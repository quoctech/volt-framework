<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Tenant\Models\TenantModel;
use Volt\Core\Tenant\Services\TenantService;

/**
 * Integration tests cho các guard P0:
 * - E1: chặn đổi workflow_state/docstatus qua update API.
 * - C1: tenant soft-delete / restore / purge logic (grace period).
 *
 * @internal
 */
final class P0GuardIntegrationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private UserEntity $testActor;
    private string $parentName = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->testActor = new UserEntity();
        $this->testActor->name = 'admin';
        $this->testActor->roles = ['admin'];

        $this->parentName = 'E-P0GUARD-00001';
        VoltDatabase::connection()->table('tab_employee')->where('name', $this->parentName)->delete();
    }

    protected function tearDown(): void
    {
        VoltDatabase::connection()->table('tab_employee')->where('name', $this->parentName)->delete();

        $db = VoltDatabase::connection();
        $db->table('sys_tenant')->whereIn('name', ['p0_guard_tenant', 'p0_guard_tenant2'])->delete();
        // Xóa hẳn cả các row soft-delete dính từ test expectException.
        $db->query("DELETE FROM sys_tenant WHERE name IN ('p0_guard_tenant', 'p0_guard_tenant2')");

        parent::tearDown();
    }

    public function testUpdateStripsWorkflowStateAndDocstatus(): void
    {
        $model = new \App\Modules\Hrms\Models\EmployeeModel();
        $model->setActor($this->testActor);
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Guard',
        ]);

        $result = $model->update($this->parentName, [
            'employee_name'  => 'Guard Updated',
            'workflow_state' => 'Approved',
            'docstatus'      => 1,
        ]);

        $this->assertTrue($result);

        $row = VoltDatabase::connection()->table('tab_employee')
            ->where('name', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertSame('Guard Updated', $row['employee_name']);
        // workflow_state/docstatus không được đổi trực tiếp qua update.
        $this->assertSame('Draft', $row['workflow_state']);
        $this->assertSame('0', (string) $row['docstatus']);
    }

    public function testTenantSoftDeleteSetsDeletedAndPurgeAt(): void
    {
        $service = new TenantService();
        $name = 'p0_guard_tenant';

        $model = new TenantModel();
        $model->delete($name, true);

        $model->insert([
            'name'      => $name,
            'label'     => 'P0 Guard',
            'db_name'   => 'volt_p0_guard',
            'db_host'   => 'localhost',
            'db_port'   => 5432,
            'is_active' => 1,
        ]);

        $service->softDelete($name);

        $trashed = $model->getTrashed();
        $found = null;
        foreach ($trashed as $tenant) {
            if ($tenant['name'] === $name) {
                $found = $tenant;
                break;
            }
        }

        $this->assertIsArray($found, 'Tenant phải nằm trong danh sách trash.');
        $this->assertNotNull($found['deleted_at']);
        $this->assertNotNull($found['purge_at']);
        $this->assertNotEmpty($found['deleted_by']);

        // Không còn xuất hiện trong danh sách active.
        $this->assertFalse($service->exists($name));

        // Restore đưa tenant trở lại.
        $service->restore($name);
        $this->assertTrue($service->exists($name));

        // Dọn dẹp.
        $model->delete($name, true);
    }

    public function testPurgeRejectsBeforeGraceExpiry(): void
    {
        $service = new TenantService();
        $name = 'p0_guard_tenant2';

        $model = new TenantModel();
        $model->delete($name, true);

        $model->insert([
            'name'      => $name,
            'label'     => 'P0 Guard 2',
            'db_name'   => 'volt_p0_guard_2',
            'db_host'   => 'localhost',
            'db_port'   => 5432,
            'is_active' => 1,
        ]);

        $service->softDelete($name);

        $this->expectException(\InvalidArgumentException::class);
        $service->purge($name, false);

        // Cleanup
        $model->delete($name, true);
    }
}
