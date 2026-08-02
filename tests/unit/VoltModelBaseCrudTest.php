<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Database\VoltDatabase;

/**
 * Integration tests cho VoltModel base CRUD (insert/update/find/delete)
 * không đi qua child table.
 *
 * @internal
 */
final class VoltModelBaseCrudTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private UserEntity $testActor;

    private string $parentName = '';

    private int $originalSeqValue = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $db = VoltDatabase::connection();

        $this->testActor = new UserEntity();
        $this->testActor->name = 'admin';
        $this->testActor->roles = ['admin'];

        $this->parentName = 'E-BASECRUD-00001';
        $db->table('tab_employee')->where('name', $this->parentName)->delete();
        $db->table('tab_employeeeducation')->where('parent', $this->parentName)->delete();

        $this->originalSeqValue = (int) ($db->table('sys_sequence')
            ->where('key', 'employee:E-2026-#####')
            ->get()
            ->getRowArray()['current_value'] ?? 0);
    }

    protected function tearDown(): void
    {
        $db = VoltDatabase::connection();
        $db->table('tab_employee')->where('name', $this->parentName)->delete();
        $db->table('tab_employeeeducation')->where('parent', $this->parentName)->delete();

        if ($this->originalSeqValue > 0) {
            $db->table('sys_sequence')->where('key', 'employee:E-2026-#####')
                ->update(['current_value' => $this->originalSeqValue]);
        }

        parent::tearDown();
    }

    public function testInsertReturnsNamePrimaryKey(): void
    {
        $model = $this->createModel();

        $result = $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Base Crud',
            'employee_age'  => 42,
        ]);

        $this->assertSame($this->parentName, $result);
    }

    public function testInsertPersistsRowWithSystemFields(): void
    {
        $this->createModel()->insert([
            'name'          => $this->parentName,
            'employee_name' => 'System Fields',
        ]);

        $row = VoltDatabase::connection()
            ->table('tab_employee')
            ->where('name', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertSame('System Fields', $row['employee_name']);
        $this->assertSame('admin', $row['owner']);
        $this->assertSame('0', (string) $row['docstatus']);
        $this->assertSame('Draft', $row['workflow_state']);
        $this->assertNotEmpty($row['creation']);
        $this->assertNotEmpty($row['modified']);
    }

    public function testInsertWithoutExplicitNameAutoGenerates(): void
    {
        $db = VoltDatabase::connection();
        $db->table('sys_sequence')->where('key', 'employee:E-2026-#####')
            ->update(['current_value' => 99000]);

        $result = $this->createModel()->insert([
            'employee_name' => 'Auto Name',
        ]);

        $this->assertSame('E-2026-99001', $result);

        $row = VoltDatabase::connection()
            ->table('tab_employee')
            ->where('name', 'E-2026-99001')
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertSame('Auto Name', $row['employee_name']);

        $db->table('tab_employee')->where('name', 'E-2026-99001')->delete();
    }

    public function testFindReturnsInsertedRecord(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Found Me',
            'employee_age'  => 27,
        ]);

        $record = $model->find($this->parentName);

        $this->assertIsArray($record);
        $this->assertSame('Found Me', $record['employee_name']);
        $this->assertSame(27, (int) $record['employee_age']);
    }

    public function testUpdateModifiesFields(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Before',
            'employee_age'  => 10,
        ]);

        $ok = $model->update($this->parentName, [
            'employee_name' => 'After',
            'employee_age'  => 99,
        ]);

        $this->assertTrue($ok);

        $row = VoltDatabase::connection()
            ->table('tab_employee')
            ->where('name', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertSame('After', $row['employee_name']);
        $this->assertSame('99', (string) $row['employee_age']);
    }

    public function testDeleteRemovesRecord(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'To Remove',
        ]);

        $this->assertTrue($model->delete($this->parentName));

        // Soft delete: row stays but is excluded from find() and marked deleted_at
        $this->assertNull($model->find($this->parentName));

        $row = VoltDatabase::connection()
            ->table('tab_employee')
            ->where('name', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertNotNull($row['deleted_at']);
    }

    public function testFindReturnsNullForMissingRecord(): void
    {
        $record = $this->createModel()->find('E-NONEXISTENT-99999');

        $this->assertNull($record);
    }

    public function testInsertWithChildKeyStripsItFromParentRow(): void
    {
        $this->createModel()->insert([
            'name'          => $this->parentName,
            'employee_name' => 'No Children',
            'education'     => [],
        ]);

        $row = VoltDatabase::connection()
            ->table('tab_employee')
            ->where('name', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertSame('No Children', $row['employee_name']);
    }

    private function createModel(): Volt\Core\Models\VoltModel
    {
        $model = new \App\Modules\Hrms\Models\EmployeeModel();
        $model->setActor($this->testActor);

        return $model;
    }
}
