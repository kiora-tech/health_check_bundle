<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Doctrine\DBAL\Connection;
use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;

/**
 * Health check for database connectivity using Doctrine DBAL.
 *
 * Verifies that the database connection is available and responsive
 * by executing a simple SELECT 1 query.
 *
 * Supports multiple database connections by providing a connection name.
 *
 * Automatically tagged with 'health_check.checker' via interface.
 */
class DatabaseHealthCheck extends AbstractHealthCheck
{
    /**
     * @param Connection $connection Doctrine DBAL connection
     * @param string     $name       Connection name (e.g., 'default', 'analytics', 'logs')
     * @param bool       $critical   Whether this check is critical
     * @param string[]   $groups     Groups this check belongs to (e.g., ['web', 'worker'])
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $name = 'default',
        private readonly bool $critical = true,
        private readonly array $groups = []
    ) {
    }

    public function getName(): string
    {
        return 'default' === $this->name ? 'database' : "database_{$this->name}";
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
            // Execute a simple query to verify connection
            $result = $this->connection->fetchOne($this->getSentinelQuery());

            if (1 !== $result && '1' !== $result) {
                return $this->createUnhealthyResult('Database query failed');
            }

            return $this->createHealthyResult('Database operational');
        } catch (\Throwable $e) {
            return $this->createUnhealthyResult('Database connection failed');
        }
    }

    /**
     * Build a platform-portable sentinel query.
     *
     * A bare "SELECT 1" is a syntax error on platforms that require a FROM
     * clause — Oracle needs "FROM DUAL", DB2 needs "FROM SYSIBM.SYSDUMMY1" —
     * so the check would report those databases as down while they are fine.
     * DBAL knows the correct form per platform; fall back to the literal when
     * the platform cannot be resolved (which needs a live connection itself).
     */
    private function getSentinelQuery(): string
    {
        try {
            return $this->connection->getDatabasePlatform()->getDummySelectSQL();
        } catch (\Throwable $e) {
            return 'SELECT 1';
        }
    }
}
