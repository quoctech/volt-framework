<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Volt\Core\Database\TableNameResolver;

/**
 * @internal
 */
final class TableNameResolverTest extends CIUnitTestCase
{
    public function testEntityPrefixesCanonicalSnake(): void
    {
        $this->assertSame('tab_employee', TableNameResolver::entity('Employee'));
        $this->assertSame('tab_employee_education', TableNameResolver::entity('EmployeeEducation'));
    }

    public function testEntitySameInputVariantNormalizesToSameResult(): void
    {
        $this->assertSame(
            TableNameResolver::entity('employee_education'),
            TableNameResolver::entity('EmployeeEducation'),
        );
    }

    public function testSystemPrefixesCanonicalSnake(): void
    {
        $this->assertSame('sys_entity', TableNameResolver::system('Entity'));
        $this->assertSame('sys_entity_field', TableNameResolver::system('EntityField'));
    }

    public function testLegacyEntityReturnsUnprefixedSnake(): void
    {
        $this->assertSame('employee', TableNameResolver::legacyEntity('Employee'));
    }

    public function testNormalizeIdentifierHandlesMixedCaseAndSeparators(): void
    {
        $this->assertSame('employee_education', TableNameResolver::normalizeIdentifier('EmployeeEducation'));
        $this->assertSame('employee_education', TableNameResolver::normalizeIdentifier('  Employee-Education  '));
        $this->assertSame('employee_education', TableNameResolver::normalizeIdentifier('employee education'));
        $this->assertSame('a_b_c', TableNameResolver::normalizeIdentifier('A_B--C'));
    }

    public function testNormalizeIdentifierStripsInvalidCharsAndUnderscores(): void
    {
        $this->assertSame('employee', TableNameResolver::normalizeIdentifier('Employee!!!'));
        $this->assertSame('a_b', TableNameResolver::normalizeIdentifier('a__b'));
        $this->assertSame('a', TableNameResolver::normalizeIdentifier('_a_'));
        $this->assertSame('', TableNameResolver::normalizeIdentifier('###'));
    }

    public function testSingleWordUntouched(): void
    {
        $this->assertSame('leave', TableNameResolver::normalizeIdentifier('Leave'));
        $this->assertSame('test', TableNameResolver::normalizeIdentifier('Test'));
    }
}
