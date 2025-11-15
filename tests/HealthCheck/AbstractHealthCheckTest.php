<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\HealthCheck;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use PHPUnit\Framework\TestCase;

class AbstractHealthCheckTest extends TestCase
{
    private ConcreteHealthCheck $healthCheck;

    protected function setUp(): void
    {
        $this->healthCheck = new ConcreteHealthCheck();
    }

    public function testCreateHealthyResult(): void
    {
        $result = $this->healthCheck->testCreateHealthyResult('All systems operational');

        $this->assertInstanceOf(HealthCheckResult::class, $result);
        $this->assertSame('test_check', $result->name);
        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertSame('All systems operational', $result->message);
        $this->assertSame(0.0, $result->duration);
        $this->assertSame([], $result->metadata);
    }

    public function testCreateHealthyResultWithMetadata(): void
    {
        $metadata = ['version' => '1.0', 'uptime' => 3600];
        $result = $this->healthCheck->testCreateHealthyResult('Service running', $metadata);

        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertSame('Service running', $result->message);
        $this->assertSame($metadata, $result->metadata);
    }

    public function testCreateUnhealthyResult(): void
    {
        $result = $this->healthCheck->testCreateUnhealthyResult('Service unavailable');

        $this->assertInstanceOf(HealthCheckResult::class, $result);
        $this->assertSame('test_check', $result->name);
        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('Service unavailable', $result->message);
        $this->assertSame(0.0, $result->duration);
        $this->assertSame([], $result->metadata);
    }

    public function testCreateUnhealthyResultWithMetadata(): void
    {
        $metadata = ['error_code' => 500, 'retry_after' => 60];
        $result = $this->healthCheck->testCreateUnhealthyResult('Connection failed', $metadata);

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('Connection failed', $result->message);
        $this->assertSame($metadata, $result->metadata);
    }

    public function testCreateDegradedResult(): void
    {
        $result = $this->healthCheck->testCreateDegradedResult('Slow response time');

        $this->assertInstanceOf(HealthCheckResult::class, $result);
        $this->assertSame('test_check', $result->name);
        $this->assertSame(HealthCheckStatus::DEGRADED, $result->status);
        $this->assertSame('Slow response time', $result->message);
        $this->assertSame(0.0, $result->duration);
        $this->assertSame([], $result->metadata);
    }

    public function testCreateDegradedResultWithMetadata(): void
    {
        $metadata = ['latency' => 250, 'threshold' => 100];
        $result = $this->healthCheck->testCreateDegradedResult('Performance degraded', $metadata);

        $this->assertSame(HealthCheckStatus::DEGRADED, $result->status);
        $this->assertSame('Performance degraded', $result->message);
        $this->assertSame($metadata, $result->metadata);
    }

    public function testFactoryMethodsUseName(): void
    {
        $healthy = $this->healthCheck->testCreateHealthyResult('OK');
        $unhealthy = $this->healthCheck->testCreateUnhealthyResult('Failed');
        $degraded = $this->healthCheck->testCreateDegradedResult('Slow');

        $this->assertSame('test_check', $healthy->name);
        $this->assertSame('test_check', $unhealthy->name);
        $this->assertSame('test_check', $degraded->name);
    }

    public function testFactoryMethodsSetDurationToZero(): void
    {
        $healthy = $this->healthCheck->testCreateHealthyResult('OK');
        $unhealthy = $this->healthCheck->testCreateUnhealthyResult('Failed');
        $degraded = $this->healthCheck->testCreateDegradedResult('Slow');

        $this->assertSame(0.0, $healthy->duration);
        $this->assertSame(0.0, $unhealthy->duration);
        $this->assertSame(0.0, $degraded->duration);
    }
}

/**
 * Concrete implementation of AbstractHealthCheck for testing purposes.
 */
class ConcreteHealthCheck extends AbstractHealthCheck
{
    public function getName(): string
    {
        return 'test_check';
    }

    public function getTimeout(): int
    {
        return 5;
    }

    public function isCritical(): bool
    {
        return false;
    }

    protected function doCheck(): HealthCheckResult
    {
        return $this->createHealthyResult('Test implementation');
    }

    // Public wrapper methods to test protected factory methods
    public function testCreateHealthyResult(string $message, array $metadata = []): HealthCheckResult
    {
        return $this->createHealthyResult($message, $metadata);
    }

    public function testCreateUnhealthyResult(string $message, array $metadata = []): HealthCheckResult
    {
        return $this->createUnhealthyResult($message, $metadata);
    }

    public function testCreateDegradedResult(string $message, array $metadata = []): HealthCheckResult
    {
        return $this->createDegradedResult($message, $metadata);
    }
}
