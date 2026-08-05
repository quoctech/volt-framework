<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Volt;

/**
 * Feature tests cho hệ thống: health check, 404 không crash,
 * rate limit toàn cục.
 *
 * @internal
 */
final class SystemSecurityFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testHealthReturnsOk(): void
    {
        $result = $this->get('api/health');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertIsArray($body);
        $this->assertSame('ok', $body['status']);
        $this->assertSame('connected', $body['checks']['database']);
    }

    public function testHealthDetailIncludesDependencies(): void
    {
        $result = $this->get('api/health/detail');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertIsArray($body);
        $this->assertSame('ok', $body['status']);
        $this->assertSame(ENVIRONMENT, $body['dependencies']['environment']);
    }

    public function testUnknownRouteThrowsPageNotFound(): void
    {
        // Feature test không render 404 (exception re-thrown), nhưng phải throw
        // PageNotFoundException thay vì lỗi crash do maskSensitiveData.
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->get('this-route-does-not-exist-xyz');
    }

    public function testRateLimitFilterConfigSanity(): void
    {
        // Config rate limit phải hợp lệ (assert lỗi config không xảy ra).
        $config = config(Volt::class);

        $this->assertGreaterThanOrEqual(1, $config->rateLimitGlobalAttempts);
        $this->assertGreaterThanOrEqual(1, $config->rateLimitGlobalWindowSeconds);
    }
}
