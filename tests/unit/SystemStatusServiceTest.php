<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Volt\Core\System\Services\SystemStatusService;

/**
 * @internal
 */
final class SystemStatusServiceTest extends CIUnitTestCase
{
    public function testSummarizeChecksReturnsErrorWhenAnyCheckFails(): void
    {
        $service = new SystemStatusService();

        $summary = $service->summarizeChecks([
            ['status' => 'ok'],
            ['status' => 'warning'],
            ['status' => 'error'],
        ]);

        $this->assertSame('error', $summary['overall']);
        $this->assertSame(1, $summary['ok']);
        $this->assertSame(1, $summary['warning']);
        $this->assertSame(1, $summary['error']);
        $this->assertSame(3, $summary['total']);
    }

    public function testSummarizeChecksReturnsWarningWhenNoErrorsExist(): void
    {
        $service = new SystemStatusService();

        $summary = $service->summarizeChecks([
            ['status' => 'ok'],
            ['status' => 'warning'],
            ['status' => 'unknown'],
        ]);

        $this->assertSame('warning', $summary['overall']);
        $this->assertSame(1, $summary['ok']);
        $this->assertSame(2, $summary['warning']);
        $this->assertSame(0, $summary['error']);
        $this->assertSame(3, $summary['total']);
    }
}
