<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Engine\MigrationCoordinator;
use Volt\Core\Engine\QueueDispatcher;
use Volt\Core\Engine\SchemaSync;
use Volt\Core\Models\QueueJobModel;
use Volt\Core\Validation\MetadataValidator;

/**
 * @internal
 */
final class MigrationCoordinatorTest extends CIUnitTestCase
{
    private MockObject&BaseConnection $dbc;
    private MigrationCoordinator $coordinator;

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $schemaResults = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $queryResults = [];

    /** @var list<object> */
    private array $indexData = [];

    /** @var list<string> Captured DDL/DML queries */
    private array $queries = [];

    /** @var array<int, array<string, mixed>> Stored sys_migration_request rows */
    private array $requestRows = [];

    private int $requestSeq = 0;

    private int $entityCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaResults = [];
        $this->queryResults = [];
        $this->indexData = [];
        $this->queries = [];
        $this->requestRows = [];
        $this->requestSeq = 0;
        $this->entityCalls = 0;

        $queueModel = $this->createMock(QueueJobModel::class);
        $queueModel->method('dispatch')->willReturn(1);
        $queue = new QueueDispatcher($queueModel);

        $this->dbc = $this->createMock(BaseConnection::class);
        $this->dbc->method('escapeIdentifiers')->willReturnCallback(
            static fn ($value) => is_array($value)
                ? array_map(static fn ($v) => (string) $v, $value)
                : (string) $value,
        );
        $this->dbc->method('escape')->willReturnCallback(
            static fn ($value) => (string) $value,
        );
        $this->dbc->method('insertID')->willReturnCallback(fn (): int => $this->requestSeq);

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

        $sync = new SchemaSync($this->dbc, new MetadataValidator(), $queue);
        $this->coordinator = new MigrationCoordinator($this->dbc, $sync);

        $this->mockTables();
    }

    private function mockTables(): void
    {
        $self = $this;

        $entityRowResult = $this->createMock(BaseResult::class);
        $entityRowResult->method('getRowArray')->willReturnCallback(function () use ($self): array {
            return ['istable' => 0, 'custom_attributes' => $self->entityCustomAttributes ?? []];
        });

        $entityBuilder = $this->createMock(BaseBuilder::class);
        $entityBuilder->method('select')->willReturnSelf();
        $entityBuilder->method('where')->willReturnSelf();
        $entityBuilder->method('get')->willReturn($entityRowResult);
        $entityBuilder->method('countAllResults')->willReturn(1);

        $countBuilder = $this->createMock(BaseBuilder::class);
        $countBuilder->method('where')->willReturnSelf();
        $countBuilder->method('countAllResults')->willReturn(1);

        $fieldResult = $this->createMock(BaseResult::class);
        $fieldResult->method('getResultArray')->willReturnCallback(
            static fn (): array => $self->fieldRows ?? [],
        );

        $fieldBuilder = $this->createMock(BaseBuilder::class);
        $fieldBuilder->method('where')->willReturnSelf();
        $fieldBuilder->method('orderBy')->willReturnSelf();
        $fieldBuilder->method('get')->willReturn($fieldResult);

        $logBuilder = $this->createMock(BaseBuilder::class);
        $logBuilder->method('insert')->willReturn(true);
        $logBuilder->method('where')->willReturnSelf();
        $logBuilder->method('update')->willReturn(true);

        $requestBuilder = $this->createMock(BaseBuilder::class);
        $requestBuilder->method('insert')->willReturnCallback(
            function (array $data) use ($self): bool {
                $self->requestSeq++;
                $self->requestRows[$self->requestSeq] = [
                    'id'           => $self->requestSeq,
                    'entity'       => (string) ($data['entity'] ?? ''),
                    'status'       => (string) ($data['status'] ?? ''),
                    'summary'      => (string) ($data['summary'] ?? '{}'),
                    'requested_by' => (string) ($data['requested_by'] ?? ''),
                    'approved_by'  => null,
                    'applied_by'   => null,
                    'approved_at'  => null,
                    'applied_at'   => null,
                    'created_at'   => '2026-08-04 00:00:00',
                ];
                return true;
            },
        );
        $requestBuilder->method('orderBy')->willReturnSelf();
        $requestBuilder->method('get')->willReturnCallback(function (...$args) use ($self): BaseResult {
            $limit = $args[0] ?? null;
            $rows = array_values($self->requestRows);
            if ($limit !== null) {
                $rows = array_slice($rows, 0, (int) $limit);
            }
            $result = $self->createMock(BaseResult::class);
            $result->method('getResultArray')->willReturn($rows);
            $result->method('getRowArray')->willReturn(end($self->requestRows) ?: null);
            return $result;
        });
        $requestBuilder->method('where')->willReturnCallback(function ($key, $value) use ($self): BaseBuilder {
            $builder = $self->createMock(BaseBuilder::class);
            $builder->method('update')->willReturnCallback(
                function (array $data) use ($self, $key, $value): bool {
                    if (($key === 'id' || $key === 'migration_id') && isset($self->requestRows[(int) $value])) {
                        $self->requestRows[(int) $value] = array_merge($self->requestRows[(int) $value], $data);
                    }
                    return true;
                },
            );
            $builder->method('get')->willReturnCallback(function () use ($self, $key, $value): BaseResult {
                $result = $self->createMock(BaseResult::class);
                $row = null;
                if (($key === 'id' || $key === 'migration_id') && isset($self->requestRows[(int) $value])) {
                    $row = $self->requestRows[(int) $value];
                }
                $result->method('getRowArray')->willReturn($row);
                $result->method('getResultArray')->willReturn($row !== null ? [$row] : []);
                return $result;
            });
            $builder->method('where')->willReturnSelf();
            $builder->method('orderBy')->willReturnSelf();
            return $builder;
        });

        $this->dbc->method('table')->willReturnCallback(
            function (string $table) use ($entityBuilder, $countBuilder, $fieldBuilder, $requestBuilder, $logBuilder): BaseBuilder {
                if ($table === 'sys_entity_field') {
                    return $fieldBuilder;
                }
                if ($table === 'sys_entity') {
                    $this->entityCalls++;
                    return $this->entityCalls <= 2 ? $entityBuilder : $countBuilder;
                }
                if ($table === 'sys_migration_request') {
                    return $requestBuilder;
                }
                if ($table === 'sys_schema_migration') {
                    return $logBuilder;
                }

                throw new RuntimeException("Unexpected table mock: {$table}");
            },
        );
    }

    public function testPreviewReturnsSummaryWithoutWriting(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'email' => ['type' => 'character varying', 'length' => 255],
        ]);
        $this->fieldRows = [
            $this->fieldRow('email', 'Data', 0),
        ];

        $result = $this->coordinator->preview('TestEntity');

        $this->assertSame('success', $result['status']);
        $this->assertSame([], $result['plan']);
        $this->assertSame(0, $result['summary']['total']);
    }

    public function testRequestNoChangesReturnsNoMigration(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'email' => ['type' => 'character varying', 'length' => 255],
        ]);
        $this->fieldRows = [
            $this->fieldRow('email', 'Data', 0),
        ];

        $result = $this->coordinator->request('TestEntity');

        $this->assertSame('success', $result['status']);
        $this->assertNull($result['migration']);
        $this->assertFalse($result['needs_approval']);
    }

    public function testRequestSafeChangeAppliesImmediately(): void
    {
        $this->givenExistingTable('tab_test_entity', [
            'name', 'docstatus', 'owner', 'creation', 'modified', 'workflow_state', 'amended_from',
        ]);
        $this->fieldRows = [
            $this->fieldRow('email', 'Data', 0),
        ];

        $result = $this->coordinator->request('TestEntity');

        $this->assertSame('success', $result['status']);
        $this->assertFalse($result['needs_approval']);
        $this->assertSame('applied', $result['safe_migration']['request']['status'] ?? '');
        $this->assertGreaterThan(0, $result['safe_migration']['request']['id'] ?? 0);

        $hasAlter = false;
        foreach ($this->queries as $q) {
            if (stripos($q, 'ALTER TABLE') !== false) {
                $hasAlter = true;
                break;
            }
        }
        $this->assertTrue($hasAlter, 'Safe add-column must be applied immediately.');
    }

    public function testRequestBreakingChangeHeldForApproval(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'amount' => ['type' => 'numeric', 'precision' => 20, 'scale' => 6],
        ]);
        $this->fieldRows = [
            $this->fieldRow('amount', 'Float', 0),
        ];

        $result = $this->coordinator->request('TestEntity', 'admin', ['allow_type_change' => true]);

        $this->assertSame('success', $result['status']);
        $this->assertTrue($result['needs_approval']);
        $this->assertSame('pending_approval', $result['migration']['status'] ?? '');
        $this->assertNotNull($result['migration']['ops'] ?? null);
        $this->assertNull($result['safe_migration']);
    }

    public function testApproveThenApplyBreakingMigration(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'amount' => ['type' => 'numeric', 'precision' => 20, 'scale' => 6],
        ]);
        $this->fieldRows = [
            $this->fieldRow('amount', 'Float', 0),
        ];

        $request = $this->coordinator->request('TestEntity', 'admin', ['allow_type_change' => true]);
        $id = (int) ($request['migration']['id'] ?? 0);
        $this->assertGreaterThan(0, $id);

        $approved = $this->coordinator->approve($id, 'admin');
        $this->assertSame('success', $approved['status']);
        $this->assertSame('approved', $approved['request']['status']);

        $applied = $this->coordinator->apply($id, 'admin');
        $this->assertSame('success', $applied['status']);
        $this->assertSame('applied', $applied['request']['status']);
    }

    public function testApplyRequiresApproval(): void
    {
        $this->givenBaseTable('tab_test_entity', [
            'amount' => ['type' => 'numeric', 'precision' => 20, 'scale' => 6],
        ]);
        $this->fieldRows = [
            $this->fieldRow('amount', 'Float', 0),
        ];

        $request = $this->coordinator->request('TestEntity', 'admin', ['allow_type_change' => true]);
        $id = (int) ($request['migration']['id'] ?? 0);

        $result = $this->coordinator->apply($id, 'admin');
        $this->assertSame('error', $result['status']);
        $this->assertStringContainsString('duyệt', $result['message']);
    }

    public function testRollbackRestoresAppliedMigration(): void
    {
        $this->givenExistingTable('tab_test_entity', [
            'name', 'docstatus', 'owner', 'creation', 'modified', 'workflow_state', 'amended_from',
        ]);
        $this->fieldRows = [
            $this->fieldRow('email', 'Data', 0),
        ];

        $request = $this->coordinator->request('TestEntity');
        $id = (int) ($request['safe_migration']['request']['id'] ?? 0);
        $this->assertGreaterThan(0, $id);

        $result = $this->coordinator->rollback($id, 'admin');
        $this->assertSame('success', $result['status']);
        $this->assertSame('rolled_back', $result['request']['status']);

        $hasInverse = false;
        foreach ($this->queries as $q) {
            if (stripos($q, 'DROP COLUMN') !== false) {
                $hasInverse = true;
                break;
            }
        }
        $this->assertTrue($hasInverse, 'Rollback must issue the inverse DROP COLUMN.');
    }

    // --- helpers -------------------------------------------------------------

    /** @var array<int, array<string, mixed>> */
    private array $fieldRows = [];

    private array $entityCustomAttributes = [];

    /** @param array<string, mixed> $extra */
    private function fieldRow(string $fieldname, string $fieldtype, int $reqd, array $extra = []): array
    {
        return array_merge([
            'parent'    => 'test_entity',
            'fieldname' => $fieldname,
            'fieldtype' => $fieldtype,
            'label'     => ucfirst($fieldname),
            'length'    => 255,
            'reqd'      => $reqd,
            'idx'       => 1,
            'options'   => '',
        ], $extra);
    }

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

    /** @param array<string, mixed> $extra */
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
}
