<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Engine\SchemaSync;
use Volt\Core\Validation\MetadataValidator;

/**
 * @internal
 */
final class SchemaSyncTest extends CIUnitTestCase
{
    private MockObject&BaseConnection $dbc;
    private SchemaSync $sync;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $schemaResults = [];

    /** @var list<string> Captured DDL/DML SQL queries */
    private array $queries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->queries = [];
        $this->schemaResults = [];
        $this->dbc = $this->createMock(BaseConnection::class);

        // query callback: handle schema queries via schemaResults, capture others
        $self = $this;
        $this->dbc->method('query')->willReturnCallback(
            function (string $sql, ...$args) use ($self): ?BaseResult {
                if (stripos($sql, 'information_schema') !== false) {
                    $binds = $args[0] ?? [];
                    $table = is_array($binds) ? (string) ($binds[0] ?? '') : '';
                    $rows = $self->schemaResults[$table] ?? [];

                    $result = $self->createMock(BaseResult::class);
                    $result->method('getResultArray')->willReturn($rows);
                    return $result;
                }

                $self->queries[] = $sql;
                return null;
            },
        );

        $this->sync = new SchemaSync($this->dbc, new MetadataValidator());
    }

    public function testSyncEntityReturnsErrorWhenMetadataEmpty(): void
    {
        $this->setupTableMocks(['istable' => 0], []);

        $result = $this->sync->syncEntity('TestEntity');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Metadata trống', $result['message']);
    }

    public function testSyncEntityCreatesTableWhenNotExists(): void
    {
        $this->setupTableMocks(['istable' => 0], [
            [
                'parent' => 'test_entity',
                'fieldname' => 'full_name',
                'fieldtype' => 'Data',
                'label' => 'Full Name',
                'length' => 100,
                'reqd' => 1,
                'idx' => 1,
                'options' => '',
            ],
        ]);

        $result = $this->sync->syncEntity('TestEntity');

        $this->assertSame('success', $result['status']);
        $this->assertStringContainsString('CREATE TABLE', $this->queries[0] ?? '');
    }

    public function testSyncEntityAddsMissingColumn(): void
    {
        $this->givenExistingTable('tab_test_entity', [
            'name', 'docstatus', 'owner', 'creation', 'modified', 'workflow_state', 'amended_from',
        ]);

        $this->setupTableMocks(['istable' => 0], [
            [
                'parent' => 'test_entity',
                'fieldname' => 'email',
                'fieldtype' => 'Data',
                'label' => 'Email',
                'length' => 255,
                'reqd' => 0,
                'idx' => 1,
                'options' => '',
            ],
        ]);

        $result = $this->sync->syncEntity('TestEntity');

        $this->assertSame('success', $result['status']);
        $hasAlter = false;
        foreach ($this->queries as $q) {
            if (stripos($q, 'ALTER TABLE') !== false) {
                $hasAlter = true;
                break;
            }
        }
        $this->assertTrue($hasAlter, 'Expected ALTER TABLE query');
    }

    /** Pre-populate schema results so information_schema returns existing columns. */
    private function givenExistingTable(string $tableName, array $columnNames): void
    {
        $rows = [];
        foreach ($columnNames as $col) {
            $rows[] = [
                'column_name' => $col,
                'data_type' => 'character varying',
                'character_maximum_length' => 100,
                'is_nullable' => 'NO',
            ];
        }
        $this->schemaResults[$tableName] = $rows;
    }

    /** Configure sys_entity and sys_entity_field table mocks. */
    private function setupTableMocks(array $entityRow, array $fieldRows): void
    {
        $rowResult = $this->createMock(BaseResult::class);
        $rowResult->method('getRowArray')->willReturn($entityRow);

        $rowBuilder = $this->createMock(BaseBuilder::class);
        $rowBuilder->method('select')->willReturnSelf();
        $rowBuilder->method('where')->willReturnSelf();
        $rowBuilder->method('get')->willReturn($rowResult);

        $countBuilder = $this->createMock(BaseBuilder::class);
        $countBuilder->method('where')->willReturnSelf();
        $countBuilder->method('countAllResults')->willReturn(1);

        $fieldResult = $this->createMock(BaseResult::class);
        $fieldResult->method('getResultArray')->willReturn($fieldRows);

        $fieldBuilder = $this->createMock(BaseBuilder::class);
        $fieldBuilder->method('where')->willReturnSelf();
        $fieldBuilder->method('orderBy')->willReturnSelf();
        $fieldBuilder->method('get')->willReturn($fieldResult);

        $this->dbc->method('table')->willReturnCallback(
            function (string $table) use ($rowBuilder, $countBuilder, $fieldBuilder): BaseBuilder {
                if ($table === 'sys_entity_field') {
                    return $fieldBuilder;
                }

                static $entityCalls = 0;
                $entityCalls++;
                return $entityCalls <= 2 ? $rowBuilder : $countBuilder;
            },
        );
    }
}
