<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Kiora\HealthCheckBundle\HealthCheck\HealthCheckInterface;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use Kiora\HealthCheckBundle\Service\HealthCheckService;

// Create mock health checks with various durations
class FastCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'database_connection';
    }

    public function check(): HealthCheckResult
    {
        usleep(50000); // 0.05 seconds

        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::HEALTHY,
            message: 'Database connection is healthy',
            duration: 0.05,
            metadata: ['connections' => 5]
        );
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function getTimeout(): int
    {
        return 5;
    }

    public function getGroups(): array
    {
        return [];
    }
}

class SlowCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 's3_storage';
    }

    public function check(): HealthCheckResult
    {
        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::HEALTHY,
            message: 'S3 storage is accessible',
            duration: 1.42,
            metadata: ['bucket' => 'my-bucket']
        );
    }

    public function isCritical(): bool
    {
        return false;
    }

    public function getTimeout(): int
    {
        return 10;
    }

    public function getGroups(): array
    {
        return [];
    }
}

class MediumCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'redis_cache';
    }

    public function check(): HealthCheckResult
    {
        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::HEALTHY,
            message: 'Redis cache is operational',
            duration: 0.25,
            metadata: ['keys' => 1234]
        );
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function getTimeout(): int
    {
        return 5;
    }

    public function getGroups(): array
    {
        return [];
    }
}

class ApiCheck implements HealthCheckInterface
{
    public function getName(): string
    {
        return 'external_api';
    }

    public function check(): HealthCheckResult
    {
        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::HEALTHY,
            message: 'External API is responding',
            duration: 0.78,
            metadata: ['endpoint' => 'https://api.example.com']
        );
    }

    public function isCritical(): bool
    {
        return false;
    }

    public function getTimeout(): int
    {
        return 10;
    }

    public function getGroups(): array
    {
        return [];
    }
}

// Create the service with all checks
$service = new HealthCheckService([
    new FastCheck(),
    new SlowCheck(),
    new MediumCheck(),
    new ApiCheck(),
]);

// Run all checks
$results = $service->runAllChecks();

// Display the results in a formatted JSON
echo "Health Check Results with Performance Statistics:\n";
echo "================================================\n\n";
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
