<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck;

/**
 * Abstract base class for health checks.
 *
 * Provides automatic timeout management, execution time measurement,
 * and exception handling. Concrete implementations only need to
 * implement the doCheck() method.
 */
abstract class AbstractHealthCheck implements HealthCheckInterface
{
    /**
     * Execute the health check with timeout and error handling.
     */
    final public function check(): HealthCheckResult
    {
        $startTime = microtime(true);
        $timeout = $this->getTimeout();

        // Set maximum execution time for this check
        $previousTimeout = ini_get('max_execution_time');
        set_time_limit($timeout);

        try {
            $result = $this->doCheck();

            // Calculate actual duration
            $duration = microtime(true) - $startTime;

            // Return result with measured duration
            return new HealthCheckResult(
                name: $result->name,
                status: $result->status,
                message: $result->message,
                duration: $duration,
                metadata: $result->metadata
            );
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::UNHEALTHY,
                message: 'Health check failed',
                duration: $duration,
                metadata: []
            );
        } finally {
            // Restore previous timeout (cast to int, 0 if false/empty)
            set_time_limit(false !== $previousTimeout ? (int) $previousTimeout : 0);
        }
    }

    /**
     * Get the groups this check belongs to.
     *
     * By default, returns an empty array which means the check belongs
     * to all groups (no filtering). Override this method to assign
     * specific groups.
     *
     * @return string[] Array of group names
     */
    public function getGroups(): array
    {
        return [];
    }

    /**
     * Perform the actual health check logic.
     *
     * This method is called by check() and is wrapped with timeout
     * and exception handling. Implementations should focus on the
     * check logic only.
     *
     * Duration will be automatically calculated, so it can be set to 0.0
     * in the returned result.
     */
    abstract protected function doCheck(): HealthCheckResult;

    /**
     * Create a healthy result.
     *
     * Factory method to reduce code duplication when creating healthy results.
     * Duration will be automatically calculated by check(), so it's set to 0.0.
     *
     * @param string               $message  Human-readable success message
     * @param array<string, mixed> $metadata Additional contextual information
     */
    protected function createHealthyResult(string $message, array $metadata = []): HealthCheckResult
    {
        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::HEALTHY,
            message: $message,
            duration: 0.0,
            metadata: $metadata
        );
    }

    /**
     * Create an unhealthy result.
     *
     * Factory method to reduce code duplication when creating unhealthy results.
     * Duration will be automatically calculated by check(), so it's set to 0.0.
     *
     * @param string               $message  Human-readable error message
     * @param array<string, mixed> $metadata Additional contextual information
     */
    protected function createUnhealthyResult(string $message, array $metadata = []): HealthCheckResult
    {
        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::UNHEALTHY,
            message: $message,
            duration: 0.0,
            metadata: $metadata
        );
    }

    /**
     * Create a degraded result.
     *
     * Factory method to reduce code duplication when creating degraded results.
     * Duration will be automatically calculated by check(), so it's set to 0.0.
     *
     * @param string               $message  Human-readable warning message
     * @param array<string, mixed> $metadata Additional contextual information
     */
    protected function createDegradedResult(string $message, array $metadata = []): HealthCheckResult
    {
        return new HealthCheckResult(
            name: $this->getName(),
            status: HealthCheckStatus::DEGRADED,
            message: $message,
            duration: 0.0,
            metadata: $metadata
        );
    }
}
