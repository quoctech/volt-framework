<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Engine\QueueDispatcher;
use Volt\Core\Engine\SchemaSync;
use Volt\Core\Models\QueueJobModel;
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

    /** @var array<string, list<array<string, mixed>>> Constraint names returned by table_constraints query */
    private array $constraintResults = [];

    private int $entityCalls = 0;

    /** @var array<string, list<array<string, mixed>>> */
    private array $queryResults = [];

    /** @var list<string> Captured queue job types dispatched via syncEntity */
    private array $dispatchedJobs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->queries = [];
        $this->schemaResults = [];
        $this->indexData = [];
        $this->entityCalls = 0;
        $this->queryResults = [];
        $this->dispatchedJobs = [];
        $this->constraintResults = [];

        $queueModel = $this->createMock(QueueJobModel::class);
        $queueModel->method('dispatch')->willReturnCallback(
            function (string $jobType, array $payload = [], array $opts = []): int {
                $this->dispatchedJobs[] = $jobType;
                return 1;
            },
        );
        $queue = new QueueDispatcher($queueModel);
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

                    if (stripos($sql, 'table_constraints') !== false) {
                        $rows = $self->constraintResults[$table] ?? [];
                    } else {
                        $rows = $self->schemaResults[$table] ?? [];
                    }

                    $result = $self->createMock(BaseResult::class);
                    $result->method('getResultArray')->willReturn($rows);
                    return $result;
                }

                foreach ($self->queryResults as $needle => $rows) {
                    if (stripos($sql, $needle) !== false) {
                        $result = $self->createMock(BaseResult::class);
                        $result->method('getResultArray')->willReturn($rows);
                        $result->method('getRow')->willReturn((object) ($rows[0] ?? []));
                        return $result;
                    }
                }

                $self->queries[] = $sql;
                return null;
            },
        );

        $this->dbc->method('getIndexData')->willReturnCallback(fn (): array => $this->indexData);
        $this->dbc->method('getForeignKeyData')->willReturn([]);

        $this->sync = new SchemaSync($this->dbc, new MetadataValidator(), $queue);
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
        $hasCreateTable = false;
        foreach ($this->queries as $q) {
            if (stripos($q, 'CREATE TABLE') !== false) {
                $hasCreateTable = true;
                break;
            }
        }
        $this->assertTrue($hasCreateTable, 'Expected CREATE TABLE query');
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
            'deleted_at'     => ['type' => 'timestamp without time zone'],
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

    public function testOrphanIndexNotDroppedWithoutPrune(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'email' => ['type' => 'character varying', 'length' => 255],
        ]);

        $index = new stdClass();
        $index->name = 'ix_test_entity_legacy';
        $index->fields = ['legacy'];
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
        $drops = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'drop_index',
        ));
        $this->assertSame([], $drops, 'Orphan index must not be dropped without --prune.');
    }

    public function testOrphanIndexDroppedWithPrune(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'email' => ['type' => 'character varying', 'length' => 255],
        ]);

        $index = new stdClass();
        $index->name = 'ix_test_entity_legacy';
        $index->fields = ['legacy'];
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

        $result = $this->sync->planEntity('TestEntity', ['prune' => true]);
        $drops = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'drop_index',
        ));
        $this->assertCount(1, $drops);
        $this->assertSame('ix_test_entity_legacy', $drops[0]['index_name']);
        $this->assertSame('breaking', $drops[0]['severity']);
    }

    public function testPlansUniqueConstraintFromCustomAttributes(): void
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
            'custom_attributes' => ['uniques' => ['email']],
        ], [$fieldRow]);

        $result = $this->sync->planEntity('TestEntity');
        $this->assertSame('success', $result['status']);

        $constraintOps = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'add_constraint',
        ));
        $this->assertCount(1, $constraintOps);
        $this->assertSame('ix_test_entity_email_uq', $constraintOps[0]['constraint_name']);
        $this->assertSame('breaking', $constraintOps[0]['severity']);
    }

    public function testSkipsExistingConstraint(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'email' => ['type' => 'character varying', 'length' => 255],
        ]);

        $this->constraintResults['tab_test_entity'] = [
            ['constraint_name' => 'ix_test_entity_email_uq'],
        ];

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
            'custom_attributes' => ['uniques' => ['email']],
        ], [$fieldRow]);

        $result = $this->sync->planEntity('TestEntity');
        $constraintOps = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'add_constraint',
        ));
        $this->assertSame([], $constraintOps, 'Existing constraint must not be re-planned.');
    }

    public function testPlansExpandContractForRequiredColumnWithDefault(): void
    {
        $this->givenBaseTable('tab_test_entity');

        $this->queryResults = [
            'SELECT 1 FROM tab_test_entity' => [['x' => 1]],
        ];

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'code',
            'fieldtype' => 'Data',
            'label' => 'Code',
            'length' => 50,
            'reqd' => 1,
            'idx' => 1,
            'options' => '',
        ];

        $this->setupTableMocks(['istable' => 0], [$fieldRow]);

        $result = $this->sync->planEntity('TestEntity', ['defaults' => ['code' => 'PENDING']]);
        $this->assertSame('success', $result['status']);

        $operations = array_map(static fn (array $op): string => $op['operation'], $result['plan']);
        $this->assertContains('add_column', $operations);
        $this->assertContains('backfill_data', $operations);
        $this->assertContains('set_not_null', $operations);

        $backfill = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'backfill_data',
        ));
        $this->assertSame('code', $backfill[0]['column']);
        $this->assertSame('code IS NULL', $backfill[0]['where']);
    }

    public function testAddsNullableColumnWhenRequiredWithoutDefault(): void
    {
        $this->givenBaseTable('tab_test_entity');

        $this->queryResults = [
            'SELECT 1 FROM tab_test_entity' => [['x' => 1]],
        ];

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'code',
            'fieldtype' => 'Data',
            'label' => 'Code',
            'length' => 50,
            'reqd' => 1,
            'idx' => 1,
            'options' => '',
        ];

        $this->setupTableMocks(['istable' => 0], [$fieldRow]);

        $result = $this->sync->planEntity('TestEntity');
        $this->assertSame('success', $result['status']);

        $addOps = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'add_column',
        ));
        $this->assertCount(1, $addOps);
        $this->assertTrue($addOps[0]['def']['null'], 'Required column without default must be added nullable first.');
        $this->assertArrayNotHasKey('backfill_data', array_flip(array_column($result['plan'], 'operation')));
    }

    public function testTypeChangePlansUsingExpression(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'qty' => ['type' => 'character varying', 'length' => 50],
        ]);

        $fieldRow = [
            'parent' => 'test_entity',
            'fieldname' => 'qty',
            'fieldtype' => 'Int',
            'label' => 'Qty',
            'length' => null,
            'reqd' => 0,
            'idx' => 1,
            'options' => '',
        ];

        $this->setupTableMocks(['istable' => 0], [$fieldRow]);

        $result = $this->sync->planEntity('TestEntity', ['allow_type_change' => true]);
        $this->assertSame('success', $result['status']);

        $alterOps = array_values(array_filter(
            $result['plan'],
            static fn (array $op): bool => $op['operation'] === 'alter_column',
        ));
        $this->assertCount(1, $alterOps);
        $this->assertSame('qty::integer', $alterOps[0]['using']);
        $this->assertSame('breaking', $alterOps[0]['severity']);
    }

    public function testInverseSqlGeneratedForReversibleOps(): void
    {
        $this->assertStringContainsString(
            'DROP COLUMN',
            (string) $this->sync->inverseSqlFor([
                'operation' => 'add_column',
                'table'     => 'tab_test_entity',
                'column'    => 'email',
            ]),
        );
        $this->assertStringContainsString(
            'DROP INDEX',
            (string) $this->sync->inverseSqlFor([
                'operation' => 'create_index',
                'table'     => 'tab_test_entity',
                'index_name' => 'ix_test_entity_email',
            ]),
        );
        $this->assertNull($this->sync->inverseSqlFor([
            'operation' => 'drop_column',
            'table'     => 'tab_test_entity',
            'column'    => 'email',
        ]), 'Destructive ops must not expose an automatic inverse.');
    }

    public function testSyncDispatchesRebuildCacheJobWhenChangesApplied(): void
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
        $this->assertSame(['rebuild_metadata_cache'], $this->dispatchedJobs);
    }

    public function testSyncDoesNotDispatchRebuildCacheJobInDryRun(): void
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

        $this->sync->syncEntity('TestEntity', ['dry_run' => true]);

        $this->assertSame([], $this->dispatchedJobs);
    }

    public function testSyncDoesNotDispatchWhenNoChanges(): void
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

        $this->setupTableMocks(['istable' => 0, 'custom_attributes' => []], [$fieldRow]);

        $this->sync->syncEntity('TestEntity');

        $this->assertSame([], $this->dispatchedJobs, 'No changes must not dispatch a rebuild job.');
    }

    public function testCheckDataReportsRowCountDuplicatesAndOrphans(): void
    {
        $childField = [
            'parent' => 'test_entity',
            'fieldname' => 'details',
            'fieldtype' => 'Table',
            'label' => 'Details',
            'length' => 0,
            'reqd' => 0,
            'idx' => 2,
            'options' => 'TestChild:separate',
        ];

        $this->setupTableMocks(['istable' => 0, 'custom_attributes' => []], [$childField]);

        $this->queryResults = [
            'GROUP BY name HAVING' => [
                ['name' => 'dup-1', 'cnt' => '2'],
                ['name' => 'dup-2', 'cnt' => '3'],
            ],
            'NOT IN (SELECT name FROM tab_test_entity)' => [['cnt' => '2']],
            'FROM tab_test_entity' => [['cnt' => '5']],
        ];

        $result = $this->sync->checkData('TestEntity');

        $this->assertSame('success', $result['status']);
        $this->assertSame(5, $result['rows']);
        $this->assertCount(2, $result['duplicates']);
        $this->assertSame('dup-1', $result['duplicates'][0]['name']);
        $this->assertSame(3, $result['duplicates'][1]['count']);
        $this->assertCount(1, $result['orphan_children']);
        $this->assertSame('testchild', $result['orphan_children'][0]['entity']);
        $this->assertSame('tab_testchild', $result['orphan_children'][0]['table']);
        $this->assertSame(2, $result['orphan_children'][0]['count']);
    }

    public function testCheckDataSkipsChildTableWhenEntityIsChild(): void
    {
        $this->setupTableMocks(['istable' => 1, 'custom_attributes' => []], [
            [
                'parent' => 'test_child',
                'fieldname' => 'school_name',
                'fieldtype' => 'Data',
                'label' => 'School',
                'length' => 255,
                'reqd' => 0,
                'idx' => 1,
                'options' => '',
            ],
        ]);

        $this->queryResults = [
            'FROM tab_test_child' => [['cnt' => '0']],
        ];

        $result = $this->sync->checkData('TestChild');

        $this->assertSame('success', $result['status']);
        $this->assertSame(0, $result['rows']);
        $this->assertSame([], $result['orphan_children']);
    }

    public function testCheckDataReturnsErrorWhenMetadataEmpty(): void
    {
        $this->setupTableMocks(['istable' => 0], []);

        $result = $this->sync->checkData('TestEntity');

        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('Metadata trống', $result['message']);
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
        $rowBuilder->method('countAllResults')->willReturn(1);

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
