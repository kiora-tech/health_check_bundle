<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Doctrine\DBAL\Connection;
use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;

/**
 * Health check for database connectivity using Doctrine DBAL.
 *
 * Verifies that the database connection is available and responsive
 * by executing a simple SELECT 1 query.
 *
 * Automatically tagged with 'health_check.checker' via interface.
 */
class DatabaseHealthCheck extends AbstractHealthCheck
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function getName(): string
    {
        return 'database';
    }

    public function getTimeout(): int
    {
        return 5;
    }

    public function isCritical(): bool
    {
        return true;
    }

    protected function doCheck(): HealthCheckResult
    {
        try {
            // Execute a simple query to verify connection
            $result = $this->connection->fetchOne('SELECT 1');

            if ($result !== 1 && $result !== '1') {
                return new HealthCheckResult(
                    name: $this->getName(),
                    status: HealthCheckStatus::UNHEALTHY,
                    message: 'Database query failed',
                    duration: 0.0,
                    metadata: []
                );
            }

            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::HEALTHY,
                message: 'Database operational',
                duration: 0.0,
                metadata: []
            );
        } catch (\Exception $e) {
            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::UNHEALTHY,
                message: 'Database connection failed',
                duration: 0.0,
                metadata: []
            );
        }
    }
}
