<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\HealthCheck;

use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use PHPUnit\Framework\TestCase;

class HealthCheckResultTest extends TestCase
{
    public function testHealthCheckResultCreation(): void
    {
        $result = new HealthCheckResult(
            name: 'test_check',
            status: HealthCheckStatus::HEALTHY,
            message: 'Test message',
            duration: 0.123,
            metadata: ['key' => 'value']
        );

        $this->assertSame('test_check', $result->name);
        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertSame('Test message', $result->message);
        $this->assertSame(0.123, $result->duration);
        $this->assertSame(['key' => 'value'], $result->metadata);
    }

    public function testHealthCheckResultWithDefaultMetadata(): void
    {
        $result = new HealthCheckResult(
            name: 'test_check',
            status: HealthCheckStatus::UNHEALTHY,
            message: 'Failed',
            duration: 0.5
        );

        $this->assertSame([], $result->metadata);
    }

    public function testHealthCheckResultIsImmutable(): void
    {
        $result = new HealthCheckResult(
            name: 'test',
            status: HealthCheckStatus::HEALTHY,
            message: 'OK',
            duration: 0.1
        );

        $this->assertInstanceOf(HealthCheckResult::class, $result);

        // Verify readonly properties cannot be modified
        // This is enforced by PHP's readonly keyword
        $this->expectException(\Error::class);
        $result->name = 'modified'; // @phpstan-ignore-line
    }
}
