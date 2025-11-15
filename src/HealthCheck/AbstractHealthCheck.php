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
}
