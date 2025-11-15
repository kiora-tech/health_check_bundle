<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;

/**
 * Health check for Redis connectivity.
 *
 * Verifies that Redis is available and responsive by sending a PING command.
 * Supports both PHP Redis extension and Predis library.
 *
 * Automatically tagged with 'health_check.checker' via interface.
 */
class RedisHealthCheck extends AbstractHealthCheck
{
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
        $redis = null;

        try {
            // Create Redis client and attempt connection
            $redis = new \Redis();
            $connected = @$redis->connect($this->host, $this->port, 2);

            if (!$connected) {
                return new HealthCheckResult(
                    name: $this->getName(),
                    status: HealthCheckStatus::UNHEALTHY,
                    message: 'Redis connection failed',
                    duration: 0.0,
                    metadata: []
                );
            }

            // Send PING command to Redis
            $response = $redis->ping();

            // Check response
            // phpredis returns true, Predis string client returns '+PONG' or 'PONG'
            $isPongValid = true === $response
                || '+PONG' === $response
                || 'PONG' === $response;

            if (!$isPongValid) {
                return new HealthCheckResult(
                    name: $this->getName(),
                    status: HealthCheckStatus::UNHEALTHY,
                    message: 'Redis ping failed',
                    duration: 0.0,
                    metadata: []
                );
            }

            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::HEALTHY,
                message: 'Redis operational',
                duration: 0.0,
                metadata: []
            );
        } catch (\Exception $e) {
            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::UNHEALTHY,
                message: 'Redis connection failed',
                duration: 0.0,
                metadata: []
            );
        } finally {
            // Close Redis connection if it was established
            if ($redis instanceof \Redis) {
                try {
                    @$redis->close();
                } catch (\Exception $e) {
                    // Ignore close errors
                }
            }
        }
    }
}
