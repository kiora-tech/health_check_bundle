<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
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
            // Pull a single entry rather than calling toArray(): that would page
            // through every object in the bucket and hold them all in memory,
            // which on a large bucket turns this check into an outage of its own.
            // Reaching the first entry already proves the bucket is listable.
            foreach ($this->filesystem->listContents('/', false) as $entry) {
                unset($entry);

                break;
            }

            return $this->createHealthyResult('S3 storage operational');
        } catch (\Throwable $e) {
            return $this->createUnhealthyResult('S3 storage connection failed');
        }
    }
}
