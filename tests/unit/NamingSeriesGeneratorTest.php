<?php

declare(strict_types=1);

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\BaseResult;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Volt\Core\Engine\NamingSeriesGenerator;

/**
 * @internal
 */
final class NamingSeriesGeneratorTest extends CIUnitTestCase
{
    private MockObject&BaseConnection $dbc;
    private NamingSeriesGenerator $generator;

    /** @var list<array{string, array<int, string>}> Captured sequence queries */
    private array $sequenceQueries = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sequenceQueries = [];
        $this->dbc = $this->createMock(BaseConnection::class);

        $self = $this;
        $this->dbc->method('query')->willReturnCallback(
            function (string $sql, ...$args) use ($self): ?BaseResult {
                if (stripos($sql, 'ON CONFLICT') !== false) {
                    $binds = $args[0] ?? [];
                    $self->sequenceQueries[] = [$sql, is_array($binds) ? array_values($binds) : []];

                    $result = $self->createMock(BaseResult::class);
                    $result->method('getNumRows')->willReturn(1);
                    $result->method('getRowArray')->willReturn(['current_value' => 42]);

                    return $result;
                }

                return null;
            },
        );

        $this->generator = new NamingSeriesGenerator($this->dbc);
    }

    public function testGenerateHashesWhenPatternEmpty(): void
    {
        $name = $this->generator->generate('', 'product');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $name);
    }

    public function testGenerateHashesWhenPatternIsHash(): void
    {
        $name = $this->generator->generate('HASH', 'product');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $name);
    }

    public function testGenerateReturnsStaticPatternWithoutSequenceToken(): void
    {
        $this->assertSame('FIXED-NAME', $this->generator->generate('FIXED-NAME', 'product'));
    }

    public function testGenerateStripsStrayDotBeforeHashToken(): void
    {
        $year = gmdate('Y');

        $name = $this->generator->generate('EMP-.YYYY.-.#####', 'employee');

        $this->assertSame("EMP-{$year}-00042", $name);
    }

    public function testGenerateStripsStrayDotBeforeLiteralDigits(): void
    {
        $year = gmdate('Y');

        $name = $this->generator->generate('EMP-.YYYY.-.00001', 'employee');

        $this->assertSame("EMP-{$year}-00001", $name);
    }

    public function testGenerateResolvesDateAndPadsSequence(): void
    {
        $year = gmdate('Y');

        $name = $this->generator->generate('EMP-.YYYY.-####', 'employee');

        $this->assertSame("EMP-{$year}-0042", $name);
    }

    public function testGenerateBuildsExpectedSequenceKey(): void
    {
        $year = gmdate('Y');

        $this->generator->generate('EMP-.YYYY.-####', 'Employee');

        $this->assertCount(1, $this->sequenceQueries);
        $this->assertSame(['employee:EMP-' . $year . '-####'], $this->sequenceQueries[0][1]);
    }

    public function testGenerateForEntityUsesCompiledMetadataFirst(): void
    {
        $compiled = ['entity' => ['autoname' => 'INV-.YYYY.-#####']];
        $year = gmdate('Y');

        $this->dbc->expects($this->never())->method('table');

        $name = $this->generator->generateForEntity('Invoice', $compiled);

        $this->assertStringStartsWith("INV-{$year}-00042", $name);
    }

    public function testGenerateForEntityFallsBackToSysEntityTable(): void
    {
        $this->givenAutonameInDb('PROD-.#####.');

        $name = $this->generator->generateForEntity('Product');

        $this->assertSame('PROD-00042.', $name);
    }

    public function testNextSequenceValueReturnsIncrementedValue(): void
    {
        $this->assertSame(42, $this->generator->nextSequenceValue('some:key'));
        $this->assertCount(1, $this->sequenceQueries);
    }

    private function givenAutonameInDb(string $autoname): void
    {
        $rowResult = $this->createMock(BaseResult::class);
        $rowResult->method('getRowArray')->willReturn(['autoname' => $autoname]);

        $builder = $this->createMock(BaseBuilder::class);
        $builder->method('select')->willReturnSelf();
        $builder->method('where')->willReturnSelf();
        $builder->method('get')->willReturn($rowResult);

        $this->dbc->method('table')->willReturn($builder);
    }
}
