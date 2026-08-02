<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Database\VoltDatabase;

/**
 * Integration tests cho VoltModel child table CRUD.
 *
 * Test với entity Employee (parent) và EmployeeEducation (child table).
 *
 * @internal
 */
final class VoltModelChildTableTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private UserEntity $testActor;

    private string $parentName = '';

    protected function setUp(): void
    {
        parent::setUp();

        $db = VoltDatabase::connection();

        $this->testActor = new UserEntity();
        $this->testActor->name = 'admin';
        $this->testActor->roles = ['admin'];

        // Clean up any leftover test data
        $this->parentName = 'E-TEST-00001';
        $db->table('tab_employee')->where('name', $this->parentName)->delete();
        $db->table('tab_employeeeducation')->where('parent', $this->parentName)->delete();
    }

    protected function tearDown(): void
    {
        $db = VoltDatabase::connection();
        $db->table('tab_employee')->where('name', $this->parentName)->delete();
        $db->table('tab_employeeeducation')->where('parent', $this->parentName)->delete();
        parent::tearDown();
    }

    public function testInsertWithChildRecords(): void
    {
        $model = $this->createModel();

        $result = $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Test User',
            'employee_age'  => 30,
            'education'     => [
                ['school_name' => 'Hanoi University', 'degree' => 'Bachelor', 'year' => 2015],
                ['school_name' => 'National University', 'degree' => 'Master', 'year' => 2018],
            ],
        ]);

        $this->assertNotFalse($result);

        // Verify parent record
        $parent = VoltDatabase::connection()
            ->table('tab_employee')
            ->where('name', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertIsArray($parent);
        $this->assertSame('Test User', $parent['employee_name']);
        $this->assertSame('30', (string) $parent['employee_age']);

        // Verify child records
        $children = VoltDatabase::connection()
            ->table('tab_employeeeducation')
            ->where('parent', $this->parentName)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(2, $children);
        $this->assertSame('Hanoi University', $children[0]['school_name']);
        $this->assertSame('Bachelor', $children[0]['degree']);
        $this->assertSame(2015, (int) $children[0]['year']);
        $this->assertSame('education', $children[0]['parentfield']);

        $this->assertSame('National University', $children[1]['school_name']);
        $this->assertSame('Master', $children[1]['degree']);
    }

    public function testFindAttachesChildRecords(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Test User',
            'education'     => [
                ['school_name' => 'School A', 'year' => 2010],
                ['school_name' => 'School B', 'year' => 2012],
                ['school_name' => 'School C', 'year' => 2014],
            ],
        ]);

        $record = $model->find($this->parentName);

        $this->assertIsArray($record);
        $this->assertSame('Test User', $record['employee_name']);
        $this->assertArrayHasKey('education', $record);
        $this->assertIsArray($record['education']);
        $this->assertCount(3, $record['education']);
        $this->assertSame('School A', $record['education'][0]['school_name']);
        $this->assertSame('School C', $record['education'][2]['school_name']);
    }

    public function testUpdateReplacesChildRecords(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Original',
            'education'     => [
                ['school_name' => 'Old School', 'year' => 2005],
            ],
        ]);

        // Update with different child set
        $model->update($this->parentName, [
            'employee_name' => 'Updated',
            'education'     => [
                ['school_name' => 'New School A', 'year' => 2020],
                ['school_name' => 'New School B', 'year' => 2022],
            ],
        ]);

        // Verify parent
        $parent = VoltDatabase::connection()
            ->table('tab_employee')
            ->where('name', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertSame('Updated', $parent['employee_name']);

        // Verify old child deleted, new children inserted
        $children = VoltDatabase::connection()
            ->table('tab_employeeeducation')
            ->where('parent', $this->parentName)
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(2, $children);
        $this->assertSame('New School A', $children[0]['school_name']);
        $this->assertSame('New School B', $children[1]['school_name']);

        // Verify no "Old School" remains
        $old = VoltDatabase::connection()
            ->table('tab_employeeeducation')
            ->where('parent', $this->parentName)
            ->where('school_name', 'Old School')
            ->get()
            ->getRowArray();

        $this->assertNull($old);
    }

    public function testUpdateRemovesAllChildRecords(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Test',
            'education'     => [
                ['school_name' => 'School X'],
                ['school_name' => 'School Y'],
            ],
        ]);

        // Update with empty children
        $model->update($this->parentName, [
            'employee_name' => 'Updated',
            'education'     => [],
        ]);

        $children = VoltDatabase::connection()
            ->table('tab_employeeeducation')
            ->where('parent', $this->parentName)
            ->get()
            ->getResultArray();

        $this->assertCount(0, $children);
    }

    public function testDeleteCascadesToChildRecords(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'To Delete',
            'education'     => [
                ['school_name' => 'Child A'],
                ['school_name' => 'Child B'],
            ],
        ]);

        $model->delete($this->parentName);

        // Soft delete: parent stays but is excluded from find(), children are
        // marked deleted_at alongside the parent
        $this->assertNull($model->find($this->parentName));

        $parent = VoltDatabase::connection()
            ->table('tab_employee')
            ->where('name', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertIsArray($parent);
        $this->assertNotNull($parent['deleted_at']);

        // Verify children soft-deleted (not physically removed)
        $children = VoltDatabase::connection()
            ->table('tab_employeeeducation')
            ->where('parent', $this->parentName)
            ->get()
            ->getResultArray();

        $this->assertCount(2, $children);
        foreach ($children as $child) {
            $this->assertNotNull($child['deleted_at']);
        }
    }

    public function testInsertWithoutChildRecords(): void
    {
        $model = $this->createModel();

        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'No Education',
        ]);

        $children = VoltDatabase::connection()
            ->table('tab_employeeeducation')
            ->where('parent', $this->parentName)
            ->get()
            ->getResultArray();

        $this->assertCount(0, $children);
    }

    public function testChildRecordsHaveCorrectSystemColumns(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Sys Col Test',
            'education'     => [
                ['school_name' => 'Test School'],
            ],
        ]);

        $child = VoltDatabase::connection()
            ->table('tab_employeeeducation')
            ->where('parent', $this->parentName)
            ->get()
            ->getRowArray();

        $this->assertIsArray($child);
        $this->assertSame($this->parentName, $child['parent']);
        $this->assertSame('education', $child['parentfield']);
        $this->assertSame('Employee', $child['parenttype']);
        $this->assertSame(1, (int) $child['idx']);
        $this->assertNotEmpty($child['name']);
        $this->assertSame('0', (string) $child['docstatus']);
    }

    public function testFindReturnsNullForDeletedParentWithOrphanedChildren(): void
    {
        $model = $this->createModel();
        $model->insert([
            'name'          => $this->parentName,
            'employee_name' => 'Orphan Test',
            'education'     => [
                ['school_name' => 'Orphan Child'],
            ],
        ]);

        $model->delete($this->parentName);

        $record = $model->find($this->parentName);
        $this->assertNull($record);
    }

    private function createModel(): Volt\Core\Models\VoltModel
    {
        $model = new \App\Modules\Hrms\Models\EmployeeModel();
        $model->setActor($this->testActor);

        return $model;
    }
}
