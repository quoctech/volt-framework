<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Volt\Core\Auth\Entities\UserEntity;
use Volt\Core\Database\TableNameResolver;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Engine\VoltMetadataCompiler;
use Volt\Core\Models\VoltModel;
use Volt\Core\Validation\MetadataValidator;

class VoltModelSoftDeleteTest extends CIUnitTestCase
{
    protected VoltModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset shared services so the metadata compiler does not reuse a
        // stale MockCache (per-test instance) from an earlier test method.
        \Config\Services::reset('voltMetadataCompiler');
        \Config\Services::reset('cache');

        $this->testActor = new UserEntity();
        $this->testActor->name = 'admin';
        $this->testActor->roles = ['admin'];

        $db = VoltDatabase::connection();

        // Ensure metadata is never stale across runs
        $compiler = new VoltMetadataCompiler($db, service('cache'), new MetadataValidator());
        $compiler->invalidateEntity('test_sd');
        $compiler->invalidateEntity('test_sd_child');

        // Entity: test_sd (soft delete by default)
        $this->upsertEntity($db, 'test_sd', ['is_submittable' => true, 'label' => 'Test SD']);

        // Child entity (separate table storage)
        $this->upsertEntity($db, 'test_sd_child', ['istable' => true, 'label' => 'Test SD Child']);

        $this->upsertField($db, 'test_sd', 'items', 'Table', 'test_sd_child:separate');

        $this->recreateTable('tab_test_sd', [
            'name'             => 'VARCHAR(100) PRIMARY KEY',
            'docstatus'        => 'SMALLINT DEFAULT 0',
            'amended_from'     => 'VARCHAR(100) DEFAULT NULL',
            'owner'            => 'VARCHAR(100) DEFAULT \'test\'',
            'creation'         => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'modified'         => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'deleted_at'       => 'TIMESTAMP DEFAULT NULL',
        ]);

        $this->recreateTable('tab_test_sd_child', [
            'name'       => 'VARCHAR(64) PRIMARY KEY',
            'parent'     => 'VARCHAR(100)',
            'parentfield'=> 'VARCHAR(100)',
            'parenttype' => 'VARCHAR(100)',
            'idx'        => 'INTEGER DEFAULT 0',
            'owner'      => 'VARCHAR(100) DEFAULT \'test\'',
            'creation'   => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'modified'   => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            'docstatus'  => 'SMALLINT DEFAULT 0',
            'school'     => 'VARCHAR(100)',
            'deleted_at' => 'TIMESTAMP DEFAULT NULL',
        ]);

        $this->model = $this->createModel();
    }

    protected function tearDown(): void
    {
        $db = VoltDatabase::connection();
        $db->query('DROP TABLE IF EXISTS tab_test_sd');
        $db->query('DROP TABLE IF EXISTS tab_test_sd_child');
        $db->table('sys_entity')->whereIn('name', ['test_sd', 'test_sd_child'])->delete();
        $db->table('sys_entity_field')->where('parent', 'test_sd')->delete();

        $compiler = new VoltMetadataCompiler($db, service('cache'), new MetadataValidator());
        $compiler->invalidateEntity('test_sd');
        $compiler->invalidateEntity('test_sd_child');

        parent::tearDown();
    }

    private function upsertEntity($db, string $name, array $attributes): void
    {
        $exists = $db->table('sys_entity')->where('name', $name)->get()->getRowArray();
        if (! is_array($exists)) {
            $db->table('sys_entity')->insert([
                'name'              => $name,
                'module'            => 'core',
                'autoname'          => 'HASH',
                'issingle'          => 0,
                'istable'           => (int) ($attributes['istable'] ?? 0),
                'custom_attributes' => json_encode($attributes),
            ]);
        }
    }

    private function upsertField($db, string $parent, string $fieldname, string $fieldtype, string $options): void
    {
        $exists = $db->table('sys_entity_field')
            ->where('parent', $parent)
            ->where('fieldname', $fieldname)
            ->get()
            ->getRowArray();

        if (is_array($exists)) {
            $db->table('sys_entity_field')
                ->where('parent', $parent)
                ->where('fieldname', $fieldname)
                ->update(['fieldtype' => $fieldtype, 'options' => $options]);
            return;
        }

        $db->table('sys_entity_field')->insert([
            'parent'    => $parent,
            'fieldname' => $fieldname,
            'label'     => $fieldname,
            'fieldtype' => $fieldtype,
            'options'   => $options,
            'reqd'      => 0,
            'read_only' => 0,
            'hidden'    => 0,
            'idx'       => 0,
        ]);
    }

    private function recreateTable(string $table, array $columns): void
    {
        $db = VoltDatabase::connection();
        $db->query("DROP TABLE IF EXISTS {$table}");
        $defs = [];
        foreach ($columns as $name => $type) {
            $defs[] = "{$name} {$type}";
        }
        $db->query('CREATE TABLE ' . $table . ' (' . implode(', ', $defs) . ')');
    }

    protected function createModel(): VoltModel
    {
        $model = new class extends VoltModel {
            protected $table = 'tab_test_sd';
            protected $primaryKey = 'name';
            protected $returnType = 'array';
            protected $useAutoIncrement = false;
            protected $protectFields = false;
            protected $allowedFields = [];
        };
        $model->setEntityName('test_sd');
        $model->setActor($this->testActor);

        return $model;
    }

    public function testDeleteSoftDeletesRow(): void
    {
        $this->model->insert(['name' => 'SD-001', 'owner' => 'test']);

        $result = $this->model->delete('SD-001');

        $this->assertTrue($result);
        $this->assertNull($this->model->find('SD-001'));

        $row = VoltDatabase::connection()
            ->table('tab_test_sd')
            ->where('name', 'SD-001')
            ->get()
            ->getRowArray();

        $this->assertIsArray($row);
        $this->assertNotNull($row['deleted_at']);
    }

    public function testFindAllExcludesSoftDeleted(): void
    {
        $this->model->insert(['name' => 'SD-010']);
        $this->model->insert(['name' => 'SD-011']);
        $this->model->delete('SD-010');

        $rows = $this->model->findAll();

        $this->assertCount(1, $rows);
        $this->assertSame('SD-011', $rows[0]['name']);
    }

    public function testRestore(): void
    {
        $this->model->insert(['name' => 'SD-020']);
        $this->model->delete('SD-020');

        $this->assertTrue($this->model->restore('SD-020'));
        $this->assertNotNull($this->model->find('SD-020'));

        $row = VoltDatabase::connection()
            ->table('tab_test_sd')
            ->where('name', 'SD-020')
            ->get()
            ->getRowArray();

        $this->assertNull($row['deleted_at']);
    }

    public function testRestoreMissingRowReturnsTrue(): void
    {
        $this->assertTrue($this->model->restore('SD-999'));
    }

    public function testDeleteWithPurgeHardDeletes(): void
    {
        $this->model->insert(['name' => 'SD-030']);

        $this->assertTrue($this->model->delete('SD-030', true));

        $row = VoltDatabase::connection()
            ->table('tab_test_sd')
            ->where('name', 'SD-030')
            ->get()
            ->getRowArray();

        $this->assertNull($row);
    }

    public function testPurgeDeletedRemovesOnlySoftDeleted(): void
    {
        $this->model->insert(['name' => 'SD-040']);
        $this->model->insert(['name' => 'SD-041']);
        $this->model->delete('SD-040');

        $this->model->purgeDeleted();

        $rows = VoltDatabase::connection()
            ->table('tab_test_sd')
            ->whereIn('name', ['SD-040', 'SD-041'])
            ->get()
            ->getResultArray();

        $this->assertCount(1, $rows);
        $this->assertSame('SD-041', $rows[0]['name']);
    }

    public function testWithDeletedIncludesSoftDeleted(): void
    {
        $this->model->insert(['name' => 'SD-050']);
        $this->model->delete('SD-050');

        $rows = $this->model->withDeleted()->findAll();

        $this->assertCount(1, $rows);
        $this->assertSame('SD-050', $rows[0]['name']);
    }

    public function testHardDeleteModeDeletesPermanently(): void
    {
        $this->model->insert(['name' => 'SD-060']);

        // Flip entity to hard-delete mode and invalidate metadata cache
        $db = VoltDatabase::connection();
        $db->table('sys_entity')
            ->where('name', 'test_sd')
            ->update(['custom_attributes' => json_encode(['hard_delete' => true])]);

        $compiler = new VoltMetadataCompiler($db, service('cache'), new MetadataValidator());
        $compiler->invalidateEntity('test_sd');

        $model = $this->createModel();

        $this->assertTrue($model->delete('SD-060'));

        $row = VoltDatabase::connection()
            ->table('tab_test_sd')
            ->where('name', 'SD-060')
            ->get()
            ->getRowArray();

        $this->assertNull($row);

        // restore() is a no-op in hard-delete mode
        $this->assertFalse($model->restore('SD-060'));
    }

    public function testChildRowsSoftDeletedWithParent(): void
    {
        $this->model->insert([
            'name'  => 'SD-100',
            'items' => [
                ['school' => 'School A'],
                ['school' => 'School B'],
            ],
        ]);

        $this->assertTrue($this->model->delete('SD-100'));

        $children = VoltDatabase::connection()
            ->table('tab_test_sd_child')
            ->where('parent', 'SD-100')
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(2, $children);
        foreach ($children as $child) {
            $this->assertNotNull($child['deleted_at']);
        }

        $this->assertTrue($this->model->restore('SD-100'));

        $children = VoltDatabase::connection()
            ->table('tab_test_sd_child')
            ->where('parent', 'SD-100')
            ->orderBy('idx', 'ASC')
            ->get()
            ->getResultArray();

        $this->assertCount(2, $children);
        foreach ($children as $child) {
            $this->assertNull($child['deleted_at']);
        }
    }
}
