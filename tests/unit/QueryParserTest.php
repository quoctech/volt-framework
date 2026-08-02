<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Volt\Core\Database\QueryParser;
use Volt\Core\Database\VoltDatabase;

/**
 * Integration tests cho QueryParser — filter, sort, phân trang, field selection.
 *
 * @internal
 */
final class QueryParserTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    private const TABLE = 'tab_query_test';

    protected function setUp(): void
    {
        parent::setUp();

        $db = VoltDatabase::connection();
        $db->query('DROP TABLE IF EXISTS ' . self::TABLE);
        $db->query("
            CREATE TABLE " . self::TABLE . " (
                name VARCHAR(100) PRIMARY KEY,
                status VARCHAR(20) DEFAULT 'Active',
                age INTEGER DEFAULT 0,
                score NUMERIC(10,2) DEFAULT 0,
                owner VARCHAR(100) DEFAULT 'test',
                creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $rows = [
            ['name' => 'A-001', 'status' => 'Active',  'age' => 25, 'score' => 85.5, 'owner' => 'alice'],
            ['name' => 'A-002', 'status' => 'Active',  'age' => 30, 'score' => 92.0, 'owner' => 'bob'],
            ['name' => 'B-001', 'status' => 'Inactive','age' => 45, 'score' => 70.0, 'owner' => 'alice'],
            ['name' => 'B-002', 'status' => 'Active',  'age' => 18, 'score' => 100.0,'owner' => 'charlie'],
            ['name' => 'C-001', 'status' => 'Pending', 'age' => 35, 'score' => 60.5, 'owner' => 'bob'],
        ];

        foreach ($rows as $row) {
            $db->table(self::TABLE)->insert($row);
        }

        // CI4 caches table field lists on the (shared) connection; drop stale
        // entries so column checks reflect the freshly recreated table.
        $db->resetDataCache();
    }

    protected function tearDown(): void
    {
        VoltDatabase::connection()->query('DROP TABLE IF EXISTS ' . self::TABLE);
        parent::tearDown();
    }

    // ========================================================================
    //  PAGINATION
    // ========================================================================

    public function testDefaultPagination(): void
    {
        $result = $this->parse([]);

        $this->assertSame(1, $result['page']);
        $this->assertSame(50, $result['perPage']);
        $this->assertSame(5, $result['total']);
        $this->assertCount(5, $result['builder']->get()->getResultArray());
    }

    public function testCustomPageAndPerPage(): void
    {
        $result = $this->parse(['page' => '2', 'per_page' => '10']);

        $this->assertSame(2, $result['page']);
        $this->assertSame(10, $result['perPage']);
        $this->assertSame(5, $result['total']);
        // offset = (2-1)*10 = 10, exceeds 5 total → 0 rows
        $rows = $result['builder']->get()->getResultArray();
        $this->assertCount(0, $rows);
    }

    public function testPerPageClampedToDefaultWhenInvalid(): void
    {
        $result = $this->parse(['per_page' => '7']); // not in PER_PAGE_OPTIONS → default 50

        $this->assertSame(50, $result['perPage']);
    }

    public function testPageMinimumOne(): void
    {
        $result = $this->parse(['page' => '0']);

        $this->assertSame(1, $result['page']);
    }

    // ========================================================================
    //  ORDER BY
    // ========================================================================

    public function testOrderByAsc(): void
    {
        $result = $this->parse(['order_by' => 'age asc']);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertSame(18, (int) $rows[0]['age']);
        $this->assertSame(45, (int) $rows[4]['age']);
    }

    public function testOrderByDesc(): void
    {
        $result = $this->parse(['order_by' => 'age desc']);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertSame(45, (int) $rows[0]['age']);
        $this->assertSame(18, (int) $rows[4]['age']);
    }

    public function testOrderByInvalidFieldFallsBack(): void
    {
        $result = $this->parse(['order_by' => 'nonexistent desc']);
        // Falls back to no explicit order — PostgreSQL default ordering
        $rows = $result['builder']->get()->getResultArray();
        $this->assertCount(5, $rows);
    }

    // ========================================================================
    //  FILTERS — JSON string
    // ========================================================================

    public function testFilterEquals(): void
    {
        $result = $this->parse([
            'filters' => '[["status","=","Active"]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame('Active', $row['status']);
        }
    }

    public function testFilterNotEquals(): void
    {
        $result = $this->parse([
            'filters' => '[["status","!=","Active"]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(2, $rows);
    }

    public function testFilterGreaterThan(): void
    {
        $result = $this->parse([
            'filters' => '[["age",">",30]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(2, $rows);
    }

    public function testFilterLessThanOrEqual(): void
    {
        $result = $this->parse([
            'filters' => '[["age","<=",25]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(2, $rows);
    }

    public function testFilterLike(): void
    {
        $result = $this->parse([
            'filters' => '[["name","like","B-"]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(2, $rows);
        $this->assertStringStartsWith('B-', $rows[0]['name']);
    }

    public function testFilterIn(): void
    {
        $result = $this->parse([
            'filters' => '[["status","in",["Active","Pending"]]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(4, $rows);
    }

    public function testFilterNotIn(): void
    {
        $result = $this->parse([
            'filters' => '[["status","not in",["Inactive"]]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(4, $rows);
    }

    public function testFilterBetween(): void
    {
        $result = $this->parse([
            'filters' => '[["age","between",[20,40]]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(3, $rows);
    }

    public function testMultipleFiltersAnd(): void
    {
        $result = $this->parse([
            'filters' => '[["status","=","Active"],["age",">",20]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(2, $rows);
    }

    public function testFilterInvalidOperatorIgnored(): void
    {
        $result = $this->parse([
            'filters' => '[["age","invalid_op",30]]',
        ]);
        $this->assertCount(5, $result['builder']->get()->getResultArray());
    }

    public function testFilterInvalidFieldIgnored(): void
    {
        $result = $this->parse([
            'filters' => '[["nonexistent","=","value"]]',
        ]);
        $this->assertCount(5, $result['builder']->get()->getResultArray());
    }

    // ========================================================================
    //  FILTERS — PHP array
    // ========================================================================

    public function testFilterAsPhpArray(): void
    {
        $result = $this->parse([
            'filters' => [['status', '=', 'Inactive']],
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(1, $rows);
    }

    // ========================================================================
    //  FREE-TEXT SEARCH (q)
    // ========================================================================

    public function testFreeTextSearchMatchesName(): void
    {
        $result = $this->parse(['q' => 'A-']);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(2, $rows);
    }

    public function testFreeTextSearchCombinedWithFilters(): void
    {
        $result = $this->parse([
            'q'       => 'B-',
            'filters' => '[["status","=","Inactive"]]',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(1, $rows);
        $this->assertSame('B-001', $rows[0]['name']);
    }

    // ========================================================================
    //  FIELD SELECTION
    // ========================================================================

    public function testFieldSelectionRestrictsColumns(): void
    {
        $result = $this->parse([
            'fields' => 'name,status',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertCount(5, $rows);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('status', $rows[0]);
        $this->assertArrayNotHasKey('age', $rows[0]);
        $this->assertArrayNotHasKey('owner', $rows[0]);
    }

    public function testFieldSelectionViaArray(): void
    {
        $result = $this->parse([
            'fields' => ['name', 'age'],
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('age', $rows[0]);
    }

    public function testInvalidFieldsInSelectionSkipped(): void
    {
        $result = $this->parse([
            'fields' => 'name,nonexistent,age',
        ]);
        $rows = $result['builder']->get()->getResultArray();

        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('age', $rows[0]);
        $this->assertArrayNotHasKey('nonexistent', $rows[0]);
    }

    // ========================================================================
    //  COMBINED
    // ========================================================================

    public function testFullQueryPipeline(): void
    {
        $result = $this->parse([
            'fields'   => 'name,age,score',
            'filters'  => '[["status","=","Active"]]',
            'order_by' => 'score desc',
            'page'     => '1',
            'per_page' => '10',
        ]);

        $this->assertSame(3, $result['total']); // 3 Active records
        $this->assertSame(1, $result['page']);
        $this->assertSame(10, $result['perPage']);

        $rows = $result['builder']->get()->getResultArray();
        $this->assertCount(3, $rows);
        $this->assertSame('B-002', $rows[0]['name']); // score 100
        $this->assertSame('A-002', $rows[1]['name']); // score 92
        $this->assertSame('A-001', $rows[2]['name']); // score 85.5
    }

    // ========================================================================
    //  HELPER
    // ========================================================================

    /**
     * @param array<string, mixed> $params
     * @return array{builder: \CodeIgniter\Database\BaseBuilder, total: int, page: int, perPage: int}
     */
    public function testSoftDeletedRowsFilteredOutWhenTableHasDeletedAt(): void
    {
        $db = VoltDatabase::connection();
        $db->query('ALTER TABLE ' . self::TABLE . ' ADD COLUMN deleted_at TIMESTAMP DEFAULT NULL');
        $db->resetDataCache();
        $db->table(self::TABLE)
            ->where('name', 'A-001')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);

        $result = $this->parse([]);

        $this->assertSame(4, $result['total']);

        $names = array_column($result['builder']->get()->getResultArray(), 'name');
        $this->assertNotContains('A-001', $names);
        $this->assertCount(4, $names);
    }

    public function testSoftDeletedFilterNotAppliedWhenColumnMissing(): void
    {
        $result = $this->parse([]);

        $this->assertSame(5, $result['total']);
    }

    private function parse(array $params): array
    {
        $db = VoltDatabase::connection();
        $builder = $db->table(self::TABLE);

        $query = new QueryParser(
            builder: $builder,
            entityName: 'query_test',
            permissionResolver: null,
            compiledMeta: [
                'fields' => [
                    ['fieldname' => 'status', 'fieldtype' => 'Data'],
                    ['fieldname' => 'age',    'fieldtype' => 'Int'],
                    ['fieldname' => 'score',  'fieldtype' => 'Float'],
                    ['fieldname' => 'owner',  'fieldtype' => 'Data'],
                ],
            ],
        );

        return $query->apply($params);
    }
}
