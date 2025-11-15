<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;

/**
 * Health check for Redis connectivity.
 *
 * Verifies that Redis is available and responsive by sending a PING command.
 * Supports both PHP Redis extension and Predis library.
 *
 * Uses persistent connections to reduce connection overhead and improve performance.
 * Connection is reused across multiple health check executions and automatically
 * recreated if it becomes disconnected.
 *
 * Automatically tagged with 'health_check.checker' via interface.
 */
class RedisHealthCheck extends AbstractHealthCheck
{
    /**
     * Persistent Redis connection instance.
     * Null when not yet connected or after connection failure.
     */
    private ?\Redis $connection = null;

    /**
     * @param string   $host     Redis host
     * @param int      $port     Redis port
     * @param bool     $critical Whether this check is critical
     * @param string[] $groups   Groups this check belongs to (e.g., ['web', 'worker'])
     */
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 6379,
        private readonly bool $critical = false,
        private readonly array $groups = []
    ) {
    }

    public function getName(): string
    {
        return 'redis';
    }

    public function getTimeout(): int
    {
        return 3;
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
            $redis = $this->getConnection();

            // Send PING command to Redis
            $response = $redis->ping();

            // Check response
            // phpredis returns true, Predis string client returns '+PONG' or 'PONG'
            $isPongValid = true === $response
                || '+PONG' === $response
                || 'PONG' === $response;

            if (!$isPongValid) {
                // Reset connection on ping failure to allow recovery
                $this->connection = null;

                return $this->createUnhealthyResult('Redis ping failed');
            }

            return $this->createHealthyResult('Redis operational');
        } catch (\Exception $e) {
            // Reset connection on failure to allow recovery on next check
            $this->connection = null;

            return $this->createUnhealthyResult('Redis connection failed');
        }
    }

    /**
     * Get or create a persistent Redis connection.
     *
     * Reuses existing connection if it's still connected, otherwise creates
     * a new persistent connection. This reduces connection overhead and
     * improves health check performance.
     *
     * @throws \RuntimeException If connection cannot be established
     */
    private function getConnection(): \Redis
    {
        // Reuse existing connection if it's still active
        if (null !== $this->connection && $this->connection->isConnected()) {
            return $this->connection;
        }

        // Create new persistent connection
        $this->connection = new \Redis();

        // Use pconnect for persistent connections across multiple health checks
        // Timeout of 2 seconds for connection establishment
        if (!@$this->connection->pconnect($this->host, $this->port, 2)) {
            $this->connection = null;

            throw new \RuntimeException(sprintf('Failed to establish persistent connection to Redis at %s:%d', $this->host, $this->port));
        }

        return $this->connection;
    }
}
