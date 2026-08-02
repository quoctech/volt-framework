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

    /** @var list<object> Index data returned by getIndexData() */
    private array $indexData = [];

    private int $entityCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queries = [];
        $this->schemaResults = [];
        $this->indexData = [];
        $this->entityCalls = 0;
        $this->dbc = $this->createMock(BaseConnection::class);

        // Forge escapes identifiers internally; stub as identity so SQL building works.
        $this->dbc->method('escapeIdentifiers')->willReturnCallback(
            static fn ($value) => is_array($value)
                ? array_map(static fn ($v) => (string) $v, $value)
                : (string) $value,
        );

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

        $this->dbc->method('getIndexData')->willReturnCallback(fn (): array => $this->indexData);
        $this->dbc->method('getForeignKeyData')->willReturn([]);

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
                'numeric_precision' => null,
                'numeric_scale' => null,
            ];
        }
        $this->schemaResults[$tableName] = $rows;
    }

    /**
     * Given an existing table with the standard base columns plus a typed column.
     *
     * @param array<string, mixed> $extra
     */
    private function givenBaseTable(string $tableName, array $extra = []): void
    {
        $base = [
            'name'           => ['type' => 'character varying', 'length' => 100],
            'docstatus'      => ['type' => 'smallint'],
            'owner'          => ['type' => 'character varying', 'length' => 100],
            'creation'       => ['type' => 'timestamp without time zone'],
            'modified'       => ['type' => 'timestamp without time zone'],
            'workflow_state' => ['type' => 'character varying', 'length' => 100],
            'amended_from'   => ['type' => 'character varying', 'length' => 100],
        ];

        $rows = [];
        foreach (array_merge($base, $extra) as $col => $def) {
            $rows[] = [
                'column_name' => $col,
                'data_type' => (string) $def['type'],
                'character_maximum_length' => $def['length'] ?? null,
                'is_nullable' => 'YES',
                'numeric_precision' => $def['precision'] ?? null,
                'numeric_scale' => $def['scale'] ?? null,
            ];
        }
        $this->schemaResults[$tableName] = $rows;
    }

    /** @return list<array<string, mixed>> */
    private function planOpsFor(string $entityName, array $fieldRows, array $opts = []): array
    {
        $this->setupTableMocks(['istable' => 0, 'custom_attributes' => []], $fieldRows);

        $result = $this->sync->planEntity($entityName, $opts);

        $this->assertSame('success', $result['status']);

        return $result['plan'];
    }

    public function testNumericTypeMatchPlansNoChange(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'amount' => ['type' => 'numeric', 'precision' => 18, 'scale' => 4],
        ]);

        $ops = $this->planOpsFor('TestEntity', [
            [
                'parent' => 'test_entity',
                'fieldname' => 'amount',
                'fieldtype' => 'Float',
                'label' => 'Amount',
                'length' => null,
                'reqd' => 0,
                'idx' => 1,
                'options' => '',
            ],
        ]);

        $this->assertSame([], $ops, 'Matching numeric column must not be planned as a change.');
    }

    public function testNumericWidenPlansSafeAlter(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'amount' => ['type' => 'numeric', 'precision' => 12, 'scale' => 2],
        ]);

        $ops = $this->planOpsFor('TestEntity', [
            [
                'parent' => 'test_entity',
                'fieldname' => 'amount',
                'fieldtype' => 'Float',
                'label' => 'Amount',
                'length' => null,
                'reqd' => 0,
                'idx' => 1,
                'options' => '',
            ],
        ]);

        $this->assertCount(1, $ops);
        $this->assertSame('alter_column', $ops[0]['operation']);
        $this->assertSame('safe', $ops[0]['severity']);
    }

    public function testNumericNarrowSkippedWithoutAllowTypeChange(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'amount' => ['type' => 'numeric', 'precision' => 20, 'scale' => 6],
        ]);

        $ops = $this->planOpsFor('TestEntity', [
            [
                'parent' => 'test_entity',
                'fieldname' => 'amount',
                'fieldtype' => 'Float',
                'label' => 'Amount',
                'length' => null,
                'reqd' => 0,
                'idx' => 1,
                'options' => '',
            ],
        ]);
        $this->assertSame([], $ops, 'Narrowing must be skipped without --allow-type-change.');
    }

    public function testNumericNarrowPlannedWithAllowTypeChange(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'amount' => ['type' => 'numeric', 'precision' => 20, 'scale' => 6],
        ]);

        $ops = $this->planOpsFor('TestEntity', [
            [
                'parent' => 'test_entity',
                'fieldname' => 'amount',
                'fieldtype' => 'Float',
                'label' => 'Amount',
                'length' => null,
                'reqd' => 0,
                'idx' => 1,
                'options' => '',
            ],
        ], ['allow_type_change' => true]);
        $this->assertCount(1, $ops);
        $this->assertSame('breaking', $ops[0]['severity']);
    }

    public function testRenameSkippedWithoutAllowRename(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'old_label' => ['type' => 'character varying', 'length' => 255],
        ]);

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'new_label',
            'fieldtype' => 'Data',
            'label' => 'New Label',
            'length' => 255,
            'reqd' => 0,
            'idx' => 1,
            'options' => '',
        ];

        $ops = $this->planOpsFor('TestEntity', [$fieldRow], ['renames' => ['old_label' => 'new_label']]);
        $renames = array_values(array_filter(
            $ops,
            static fn (array $op): bool => $op['operation'] === 'rename_column',
        ));
        $this->assertSame([], $renames, 'Rename must be skipped without --allow-rename.');
    }

    public function testRenameColumnPlannedWhenAllowed(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'old_label' => ['type' => 'character varying', 'length' => 255],
        ]);

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'new_label',
            'fieldtype' => 'Data',
            'label' => 'New Label',
            'length' => 255,
            'reqd' => 0,
            'idx' => 1,
            'options' => '',
        ];

        $ops = $this->planOpsFor('TestEntity', [$fieldRow], [
            'renames' => ['old_label' => 'new_label'],
            'allow_rename' => true,
        ]);
        $renames = array_values(array_filter(
            $ops,
            static fn (array $op): bool => $op['operation'] === 'rename_column',
        ));
        $this->assertCount(1, $renames);
        $this->assertSame('old_label', $renames[0]['from']);
        $this->assertSame('new_label', $renames[0]['to']);
    }

    public function testOrphanColumnNotDroppedWithoutPrune(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'legacy_col' => ['type' => 'character varying', 'length' => 100],
        ]);

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'email',
            'fieldtype' => 'Data',
            'label' => 'Email',
            'length' => 255,
            'reqd' => 0,
            'idx' => 1,
            'options' => '',
        ];

        $ops = $this->planOpsFor('TestEntity', [$fieldRow]);
        $drops = array_values(array_filter(
            $ops,
            static fn (array $op): bool => $op['operation'] === 'drop_column',
        ));
        $this->assertSame([], $drops, 'Orphan column must not be dropped without --prune.');
    }

    public function testOrphanColumnDroppedWithPrune(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'legacy_col' => ['type' => 'character varying', 'length' => 100],
        ]);

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'email',
            'fieldtype' => 'Data',
            'label' => 'Email',
            'length' => 255,
            'reqd' => 0,
            'idx' => 1,
            'options' => '',
        ];

        $ops = $this->planOpsFor('TestEntity', [$fieldRow], ['prune' => true]);
        $drops = array_values(array_filter(
            $ops,
            static fn (array $op): bool => $op['operation'] === 'drop_column',
        ));
        $this->assertCount(1, $drops);
        $this->assertSame('legacy_col', $drops[0]['column']);
        $this->assertSame('breaking', $drops[0]['severity']);
    }

    public function testPlansIndexFromCustomAttributes(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'email' => ['type' => 'character varying', 'length' => 255],
        ]);

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'email',
            'fieldtype' => 'Data',
            'label' => 'Email',
            'length' => 255,
            'reqd' => 0,
            'idx' => 1,
            'options' => '',
        ];

        $this->setupTableMocks([
            'istable' => 0,
            'custom_attributes' => ['indexes' => ['email']],
        ], [$fieldRow]);

        $result = $this->sync->planEntity('TestEntity');
        $this->assertSame('success', $result['status']);

        $indexOps = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'create_index',
        ));
        $this->assertCount(1, $indexOps);
        $this->assertSame('ix_test_entity_email', $indexOps[0]['index_name']);
    }

    public function testSkipsIndexThatAlreadyExists(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'email' => ['type' => 'character varying', 'length' => 255],
        ]);

        $index = new stdClass();
        $index->name = 'ix_test_entity_email';
        $index->fields = ['email'];
        $this->indexData = [$index];

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'email',
            'fieldtype' => 'Data',
            'label' => 'Email',
            'length' => 255,
            'reqd' => 0,
            'idx' => 1,
            'options' => '',
        ];

        $this->setupTableMocks([
            'istable' => 0,
            'custom_attributes' => ['indexes' => ['email']],
        ], [$fieldRow]);

        $result = $this->sync->planEntity('TestEntity');
        $this->assertSame('success', $result['status']);

        $indexOps = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'create_index',
        ));
        $this->assertSame([], $indexOps, 'Existing index must not be re-planned.');
    }

    /** Configure sys_entity and sys_entity_field table mocks. */
    private function setupTableMocks(array $entityRow, array $fieldRows): void
    {
        $this->entityCalls = 0;

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

                $this->entityCalls++;
                return $this->entityCalls <= 2 ? $rowBuilder : $countBuilder;
            },
        );
    }
}
