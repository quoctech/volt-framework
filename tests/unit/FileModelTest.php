<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Database\VoltDatabase;
use Volt\Core\Models\FileModel;

/**
 * Integration tests cho FileModel (sys_file).
 *
 * @internal
 */
final class FileModelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private string $prefix = 'FTEST-';

    private array $cleanupNames = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupNames = [];
        $this->cleanupRows();
    }

    protected function tearDown(): void
    {
        $this->cleanupRows();
        parent::tearDown();
    }

    public function testInsertAndFind(): void
    {
        $model = $this->createModel();
        $name = $this->nextName();

        $ok = $model->insert([
            'name'              => $name,
            'file_name'         => 'report.pdf',
            'file_path'         => '2026/08/report.pdf',
            'file_size'         => 2048,
            'file_type'         => 'application/pdf',
            'attached_to_entity' => 'Employee',
            'attached_to_name'   => 'E-2026-00001',
            'attached_to_field'  => 'cv',
        ]);
        $this->assertNotFalse($ok);

        $row = $model->find($name);
        $this->assertIsArray($row);
        $this->assertSame('report.pdf', $row['file_name']);
        $this->assertSame('Employee', $row['attached_to_entity']);
        $this->assertSame('2026/08/report.pdf', $row['file_path']);
    }

    public function testFindByEntityFiltersByField(): void
    {
        $model = $this->createModel();
        $name = $this->nextName();

        $model->insert([
            'name'              => $name,
            'file_name'         => 'a.txt',
            'file_path'         => '2026/08/a.txt',
            'file_size'         => 10,
            'file_type'         => 'text/plain',
            'attached_to_entity' => 'Leave',
            'attached_to_name'   => 'L-2026-00001',
            'attached_to_field'  => 'attachment',
        ]);
        $model->insert([
            'name'              => $this->nextName(),
            'file_name'         => 'b.txt',
            'file_path'         => '2026/08/b.txt',
            'file_size'         => 20,
            'file_type'         => 'text/plain',
            'attached_to_entity' => 'Leave',
            'attached_to_name'   => 'L-2026-00001',
            'attached_to_field'  => 'receipt',
        ]);
        $model->insert([
            'name'              => $this->nextName(),
            'file_name'         => 'c.txt',
            'file_path'         => '2026/08/c.txt',
            'file_size'         => 30,
            'file_type'         => 'text/plain',
            'attached_to_entity' => 'Leave',
            'attached_to_name'   => 'L-2026-00002',
            'attached_to_field'  => 'attachment',
        ]);

        $fieldFiltered = $model->findByEntity('Leave', 'L-2026-00001', 'attachment');
        $this->assertCount(1, $fieldFiltered);
        $this->assertSame('a.txt', $fieldFiltered[0]['file_name']);

        $all = $model->findByEntity('Leave', 'L-2026-00001');
        $this->assertCount(2, $all);
    }

    public function testDeleteByEntityRemovesOnlyMatchingRows(): void
    {
        $model = $this->createModel();
        $model->insert($this->row('keep', 'Employee', 'E-2026-00001', 'doc1'));
        $model->insert($this->row('remove', 'Employee', 'E-2026-00001', 'doc2'));
        $model->insert($this->row('other', 'Employee', 'E-2026-00002', 'doc1'));

        $model->deleteByEntity('Employee', 'E-2026-00001');

        $this->assertNull($model->find('FTEST-keep'));
        $this->assertNull($model->find('FTEST-remove'));
        $this->assertNotNull($model->find('FTEST-other'));
    }

    public function testDeleteByEntityWithField(): void
    {
        $model = $this->createModel();
        $model->insert($this->row('one', 'Employee', 'E-2026-00001', 'cv'));
        $model->insert($this->row('two', 'Employee', 'E-2026-00001', 'photo'));

        $model->deleteByEntity('Employee', 'E-2026-00001', 'cv');

        $this->assertNull($model->find('FTEST-one'));
        $this->assertNotNull($model->find('FTEST-two'));
    }

    public function testDeleteFileWithRecordReturnsFalseForMissing(): void
    {
        $this->assertFalse($this->createModel()->deleteFileWithRecord('FTEST-NONEXISTENT-999'));
    }

    public function testDeleteFileWithRecordDeletesRow(): void
    {
        $model = $this->createModel();
        $model->insert($this->row('gone', 'Employee', 'E-2026-00001', 'cv'));

        $this->assertTrue($model->deleteFileWithRecord('FTEST-gone'));
        $this->assertNull($model->find('FTEST-gone'));
    }

    private function createModel(): FileModel
    {
        return new FileModel();
    }

    private function row(string $suffix, string $entity, string $name, string $field): array
    {
        $full = $this->prefix . $suffix;
        $this->cleanupNames[] = $full;

        return [
            'name'              => $full,
            'file_name'         => $suffix . '.bin',
            'file_path'         => '2026/08/' . $suffix . '.bin',
            'file_size'         => 1,
            'file_type'         => 'application/octet-stream',
            'attached_to_entity' => $entity,
            'attached_to_name'   => $name,
            'attached_to_field'  => $field,
            'is_private'        => 1,
        ];
    }

    private function nextName(): string
    {
        $name = $this->prefix . bin2hex(random_bytes(6));
        $this->cleanupNames[] = $name;

        return $name;
    }

    private function cleanupRows(): void
    {
        if ($this->cleanupNames === []) {
            VoltDatabase::connection()->table('sys_file')
                ->like('name', $this->prefix, 'after')
                ->delete();

            return;
        }

        foreach ($this->cleanupNames as $name) {
            VoltDatabase::connection()->table('sys_file')->where('name', $name)->delete();
        }
    }
}
