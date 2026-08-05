<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Config\Volt;
use Volt\Core\System\Services\AlertService;

/**
 * @internal
 */
final class AlertServiceTest extends CIUnitTestCase
{
    public function testDisabledWhenNoWebhookUrl(): void
    {
        $service = new AlertService($this->voltConfig(''));

        $this->assertFalse($service->enabled());
        $this->assertFalse($service->send('error', 't', 'm'));
    }

    public function testEnabledWhenWebhookUrlSet(): void
    {
        $service = new AlertService($this->voltConfig('https://hooks.example.com/alert'));

        $this->assertTrue($service->enabled());
    }

    public function testSendIgnoredBelowMinLevel(): void
    {
        // minLevel = error (mặc định). warning < error → không gửi.
        $service = new AlertService($this->voltConfig('https://hooks.example.com/alert', 'error'));

        $this->assertFalse($service->send('warning', 't', 'm'));
    }

    public function testSendAttemptedAtOrAboveMinLevel(): void
    {
        $service = new AlertService($this->voltConfig('http://127.0.0.1:1/unreachable', 'error'));

        // URL không truy cập được → trả false mà không ném exception.
        $this->assertFalse($service->send('error', 't', 'm'));
    }

    private function voltConfig(string $url, string $minLevel = 'error'): Volt
    {
        return new class ($url, $minLevel) extends Volt {
            public function __construct(string $url, string $minLevel)
            {
                $this->alertWebhookUrl = $url;
                $this->alertWebhookSecret = 'secret';
                $this->alertMinLevel = $minLevel;
            }
        };
    }
}
