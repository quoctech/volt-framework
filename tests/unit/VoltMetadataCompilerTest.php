<?php

declare(strict_types=1);

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Engine\VoltMetadataCompiler;

/**
 * @internal
 */
final class VoltMetadataCompilerTest extends CIUnitTestCase
{
    private MockObject&BaseConnection $dbc;

    private MockObject&CacheInterface $cache;

    /** @var array<string, list<array<string, mixed>>> table name => rows */
    private array $tableRows = [];

    /** @var array<string, list<array{0:string,1:mixed}>> table name => where conditions */
    private array $builderWheres = [];

    /** @var array<string, array<string, mixed>> cache store */
    private array $cacheStore = [];

    private ?BaseBuilder $lastBuilder = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tableRows = [];
        $this->builderWheres = [];
        $this->cacheStore = [];

        $self = $this;
        $this->dbc = $this->createMock(BaseConnection::class);
        $this->dbc->method('table')->willReturnCallback(
            function (string $table) use ($self): BaseBuilder {
                $self->builderWheres[$table] = [];

                $builder = $self->createMock(BaseBuilder::class);
                $builder->method('where')->willReturnCallback(
                    function (string $field, mixed $value) use ($self, $table): BaseBuilder {
                        $self->builderWheres[$table][] = [$field, $value];
                        return $self->lastBuilder();
                    },
                );
                $builder->method('orWhere')->willReturnCallback(
                    function (string $field, mixed $value) use ($self, $table): BaseBuilder {
                        $self->builderWheres[$table][] = [$field, $value, 'or'];
                        return $self->lastBuilder();
                    },
                );
                $builder->method('select')->willReturnSelf();
                $builder->method('groupStart')->willReturnSelf();
                $builder->method('groupEnd')->willReturnSelf();
                $builder->method('orderBy')->willReturnSelf();
                $builder->method('get')->willReturnCallback(
                    function () use ($self, $table): BaseResult {
                        $rows = $self->filterRows($table, $self->tableRows[$table] ?? []);

                        $result = $self->createMock(BaseResult::class);
                        $result->method('getResultArray')->willReturn($rows);
                        $result->method('getRowArray')->willReturn($rows[0] ?? null);
                        return $result;
                    },
                );

                $self->lastBuilder = $builder;
                return $builder;
            },
        );

        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->method('get')->willReturnCallback(
            fn (string $key) => $this->cacheStore[$key] ?? null,
        );
        $this->cache->method('save')->willReturnCallback(
            function (string $key, mixed $value, int $ttl): bool {
                $this->cacheStore[$key] = $value;
                return true;
            },
        );
        $this->cache->method('delete')->willReturnCallback(
            function (string $key): bool {
                unset($this->cacheStore[$key]);
                return true;
            },
        );
    }

    public function testCompileEntityBuildsFullStructure(): void
    {
        $this->givenEntity('Employee', 'hrms');
        $this->givenFields('Employee', [
            [
                'parent' => 'Employee', 'fieldname' => 'full_name', 'fieldtype' => 'Data',
                'label' => 'Full Name', 'reqd' => 1, 'idx' => 1, 'options' => '',
            ],
            [
                'parent' => 'Employee', 'fieldname' => 'amount', 'fieldtype' => 'Currency',
                'label' => 'Amount', 'reqd' => 0, 'idx' => 2, 'options' => '',
            ],
        ]);
        $this->givenCustom('Employee', []);

        $compiled = $this->compiler()->compileEntity('Employee');

        $this->assertSame('Employee', $compiled['entity']['name']);
        $this->assertSame('hrms', $compiled['entity']['module']);
        $this->assertSame(['full_name', 'amount'], array_keys($compiled['fields']));
        $this->assertSame(['full_name', 'amount'], $compiled['field_order']);
        $this->assertSame(['full_name', 'amount'], $compiled['main_fields']);
        $this->assertSame(['full_name'], $compiled['derived']['required_fields']);
        $this->assertSame(2, $compiled['derived']['main_field_count']);
        $this->assertFalse($compiled['workflow']['active']);
    }

    public function testCompileEntityDetectsChildTableField(): void
    {
        $this->givenEntity('Employee', 'hrms');
        $this->givenFields('Employee', [
            [
                'parent' => 'Employee', 'fieldname' => 'education', 'fieldtype' => 'Table',
                'label' => 'Education', 'reqd' => 0, 'idx' => 1,
                'options' => 'EmployeeEducation:separate_table',
            ],
        ]);
        $this->givenCustom('Employee', []);

        $compiled = $this->compiler()->compileEntity('Employee');

        $this->assertSame(['education'], $compiled['child_fields']);
        $this->assertSame('employeeeducation', $compiled['child_tables']['education']['child_entity']);
        $this->assertSame('separate_table', $compiled['child_tables']['education']['storage']);
        $this->assertSame([], $compiled['main_fields']);
    }

    public function testCompileEntityAppliesRoleCustomMetaAsPatch(): void
    {
        $this->givenEntity('Employee', 'hrms');
        $this->givenFields('Employee', [
            [
                'parent' => 'Employee', 'fieldname' => 'full_name', 'fieldtype' => 'Data',
                'label' => 'Full Name', 'reqd' => 0, 'idx' => 1, 'options' => '',
            ],
        ]);
        $this->givenCustom('Employee', [
            [
                'entity_name' => 'Employee',
                'apply_to_role' => null,
                'custom_meta' => ['entity' => ['custom_attributes' => ['is_submittable' => 1]]],
            ],
            [
                'entity_name' => 'Employee',
                'apply_to_role' => 'hr_manager',
                'custom_meta' => ['fields' => ['full_name' => ['reqd' => 1]]],
            ],
        ]);

        $global = $this->compiler()->compileEntity('Employee');
        $this->assertSame(1, $global['entity']['custom_attributes']['is_submittable']);
        $this->assertSame(0, $global['fields']['full_name']['reqd']);

        $role = $this->compiler()->compileEntity('Employee', 'hr_manager');
        $this->assertSame(1, $role['entity']['custom_attributes']['is_submittable']);
        $this->assertSame(1, $role['fields']['full_name']['reqd']);
    }

    public function testCompileEntityUsesCacheOnSecondCall(): void
    {
        $this->givenEntity('Employee', 'hrms');
        $this->givenFields('Employee', [
            [
                'parent' => 'Employee', 'fieldname' => 'full_name', 'fieldtype' => 'Data',
                'label' => 'Full Name', 'reqd' => 0, 'idx' => 1, 'options' => '',
            ],
        ]);
        $this->givenCustom('Employee', []);

        $compiler = $this->compiler();
        $first = $compiler->compileEntity('Employee');
        $second = $compiler->compileEntity('Employee');

        $this->assertSame($first['cache']['key'], $second['cache']['key']);
        $this->assertNotEmpty($second['derived']['field_names']);
    }

    public function testCompileEntityThrowsWhenEntityMissing(): void
    {
        $this->givenFields('Employee', []);
        $this->givenCustom('Employee', []);

        $this->expectException(InvalidArgumentException::class);
        $this->compiler()->compileEntity('Employee');
    }

    public function testCompileEntityRejectsInvalidName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compiler()->compileEntity('invalid name!');
    }

    public function testInvalidateEntityDeletesIndexedKeys(): void
    {
        $this->givenEntity('Employee', 'hrms');
        $this->givenFields('Employee', [
            [
                'parent' => 'Employee', 'fieldname' => 'full_name', 'fieldtype' => 'Data',
                'label' => 'Full Name', 'reqd' => 0, 'idx' => 1, 'options' => '',
            ],
        ]);
        $this->givenCustom('Employee', []);

        $compiler = $this->compiler();
        $compiler->compileEntity('Employee');
        $compiler->compileEntity('Employee', 'hr_manager');

        $this->assertCount(3, array_keys($this->cacheStore));

        $this->assertTrue($compiler->invalidateEntity('Employee'));

        $remaining = array_keys($this->cacheStore);
        $this->assertSame([], $remaining, 'All entity + index cache keys should be removed');
    }

    public function testWarmAllReturnsSummary(): void
    {
        $this->givenEntity('Employee', 'hrms');
        $this->givenFields('Employee', [
            [
                'parent' => 'Employee', 'fieldname' => 'full_name', 'fieldtype' => 'Data',
                'label' => 'Full Name', 'reqd' => 0, 'idx' => 1, 'options' => '',
            ],
        ]);
        $this->givenCustom('Employee', []);

        $this->tableRows['sys_entity'] = [
            ['name' => 'Employee'],
        ];

        $summary = $this->compiler()->warmAll();

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['warmed']);
        $this->assertSame(0, $summary['failed']);
        $this->assertSame([], $summary['errors']);
    }

    private function compiler(): VoltMetadataCompiler
    {
        return new VoltMetadataCompiler($this->dbc, $this->cache);
    }

    private function lastBuilder(): BaseBuilder
    {
        return $this->lastBuilder ?? throw new RuntimeException('No active builder');
    }

    /**
     * Emulate the WHERE semantics the compiler issues:
     *  - AND conditions on distinct fields
     *  - an OR-group for apply_to_role (null = global, or specific role)
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private function filterRows(string $table, array $rows): array
    {
        $wheres = $this->builderWheres[$table] ?? [];

        if ($wheres === []) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            function (array $row) use ($wheres): bool {
                $andConditions = [];
                $roleOrValues = [];

                foreach ($wheres as $where) {
                    [$field, $value] = $where;
                    $isOr = ($where[2] ?? 'and') === 'or';

                    if ($field === 'apply_to_role') {
                        $roleOrValues[] = $value;
                        continue;
                    }

                    if ($isOr) {
                        continue;
                    }

                    $andConditions[] = [$field, $value];
                }

                if ($roleOrValues !== []) {
                    $matchesRole = false;
                    foreach ($roleOrValues as $roleValue) {
                        $rowValue = $row['apply_to_role'] ?? null;
                        if (($roleValue === null && $rowValue === null) || $rowValue === $roleValue) {
                            $matchesRole = true;
                            break;
                        }
                    }

                    if (! $matchesRole) {
                        return false;
                    }
                }

                foreach ($andConditions as [$field, $value]) {
                    if (preg_match('/^LOWER\((.+)\)$/', $field, $m) === 1) {
                        $field = $m[1];
                    }

                    $rowValue = $row[$field] ?? null;
                    if ($value === null) {
                        if ($rowValue !== null) {
                            return false;
                        }
                    } elseif (strtolower((string) $rowValue) !== strtolower((string) $value)) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    private function givenEntity(string $name, string $module): void
    {
        $this->tableRows['sys_entity'] = [[
            'name' => $name,
            'module' => $module,
            'issingle' => 0,
            'istable' => 0,
            'autoname' => '',
            'states' => [],
            'custom_attributes' => [],
        ]];
    }

    /** @param list<array<string, mixed>> $fields */
    private function givenFields(string $entity, array $fields): void
    {
        $this->tableRows['sys_entity_field'] = $fields;
    }

    /** @param list<array<string, mixed>> $custom */
    private function givenCustom(string $entity, array $custom): void
    {
        $this->tableRows['sys_entity_custom'] = $custom;
    }
}
