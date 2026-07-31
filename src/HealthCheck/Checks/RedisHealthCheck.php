<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;

/**
 * Health check for Redis connectivity.
 *
 * Verifies that Redis is available and responsive by sending a PING command.
 *
 * By default the check manages its own persistent connection to $host:$port,
 * reusing it across executions and recreating it when it drops. Applications
 * that already have a configured client — a tuned phpredis instance, a TLS or
 * authenticated connection — should pass it as $client instead, so the check
 * probes the same connection the application actually uses.
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
     * Whether the connection was supplied by the caller.
     *
     * An injected client is owned by the application, so it is never discarded
     * on failure: this check has no way to rebuild an equivalent one.
     */
    private readonly bool $hasInjectedConnection;

    /**
     * @param string      $host           Redis host
     * @param int         $port           Redis port
     * @param bool        $critical       Whether this check is critical
     * @param string[]    $groups         Groups this check belongs to (e.g., ['web', 'worker'])
     * @param \Redis|null $client         Optional pre-configured client to probe instead of connecting
     * @param float       $connectTimeout Connection timeout in seconds
     */
    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 6379,
        private readonly bool $critical = false,
        private readonly array $groups = [],
        ?\Redis $client = null,
        private readonly float $connectTimeout = 2.0
    ) {
        $this->connection = $client;
        $this->hasInjectedConnection = null !== $client;
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

            // phpredis returns true (or '+PONG' in multi/raw mode)
            $isPongValid = true === $response
                || '+PONG' === $response
                || 'PONG' === $response;

            if (!$isPongValid) {
                $this->discardConnection();

                return $this->createUnhealthyResult('Redis ping failed');
            }

            return $this->createHealthyResult('Redis operational');
        } catch (\Throwable $e) {
            // Catches \Throwable rather than \Exception: a missing ext-redis
            // raises an Error on `new \Redis()`, which must degrade to an
            // unhealthy result instead of escaping as a fatal error.
            $this->discardConnection();

            return $this->createUnhealthyResult('Redis connection failed');
        }
    }

    /**
     * Get or create a persistent Redis connection.
     *
     * Reuses an existing connection while it is still live, otherwise opens a
     * new persistent one. This keeps repeated probes cheap.
     *
     * @throws \RuntimeException If connection cannot be established
     */
    private function getConnection(): \Redis
    {
        // Reuse existing connection if it's still active
        if (null !== $this->connection && $this->connection->isConnected()) {
            return $this->connection;
        }

        if ($this->hasInjectedConnection && null !== $this->connection) {
            // The application owns this client; probe it as-is and let ping()
            // surface whatever is wrong rather than reconnecting behind its back.
            return $this->connection;
        }

        if (!class_exists(\Redis::class)) {
            throw new \RuntimeException('The redis extension is not installed');
        }

        $this->connection = new \Redis();

        // Use pconnect for persistent connections across multiple health checks
        if (!@$this->connection->pconnect($this->host, $this->port, $this->connectTimeout)) {
            $this->connection = null;

            throw new \RuntimeException(sprintf('Failed to establish persistent connection to Redis at %s:%d', $this->host, $this->port));
        }

        return $this->connection;
    }

    /**
     * Drop a self-managed connection so the next check can reconnect.
     */
    private function discardConnection(): void
    {
        if (!$this->hasInjectedConnection) {
            $this->connection = null;
        }
    }
}
