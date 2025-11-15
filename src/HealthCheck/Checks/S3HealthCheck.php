<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use League\Flysystem\FilesystemOperator;

/**
 * Health check for S3/MinIO storage connectivity.
 *
 * Verifies that S3-compatible storage is available and accessible
 * by attempting to list files in the bucket.
 *
 * Automatically tagged with 'health_check.checker' via interface.
 */
class S3HealthCheck extends AbstractHealthCheck
{
    /**
     * @param FilesystemOperator $filesystem Flysystem filesystem instance
     * @param string             $name       Optional custom name for this check
     * @param bool               $critical   Whether this check is critical
     * @param string[]           $groups     Groups this check belongs to (e.g., ['web', 'worker'])
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly string $name = 's3',
        private readonly bool $critical = false,
        private readonly array $groups = []
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTimeout(): int
    {
        return 5;
    }

    public function isCritical(): bool
    {
        return $this->critical;
    }

    public function getGroups(): array
    {
        return $this->groups;
    }

    protected function doCheck(): HealthCheckResult
    {
        try {
            // Try to list files (limit to 1) to verify bucket access
            $this->filesystem->listContents('/', false)->toArray();

            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::HEALTHY,
                message: 'S3 storage operational',
                duration: 0.0,
                metadata: []
            );
        } catch (\Exception $e) {
            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::UNHEALTHY,
                message: 'S3 storage connection failed',
                duration: 0.0,
                metadata: []
            );
        }
    }
}
