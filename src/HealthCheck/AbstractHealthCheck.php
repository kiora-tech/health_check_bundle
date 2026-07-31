<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for health checks.
 *
 * Provides automatic timeout management, execution time measurement,
 * and exception handling. Concrete implementations only need to
 * implement the doCheck() method.
 *
 * Implements LoggerAwareInterface: when MonologBundle is installed, Symfony
 * injects the logger automatically. Failures are then recorded server-side
 * with their full exception detail while HTTP responses stay generic.
 */
abstract class AbstractHealthCheck implements HealthCheckInterface, LoggerAwareInterface
{
    protected ?LoggerInterface $logger = null;

    /**
     * Inject a PSR-3 logger used to record check failures internally.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Execute the health check with timeout and error handling.
     */
    final public function check(): HealthCheckResult
    {
        $startTime = microtime(true);
        $timeout = $this->getTimeout();

        $previousTimeout = $this->applyTimeLimit($timeout);

        try {
            $result = $this->doCheck();
            $duration = microtime(true) - $startTime;

            return $this->finalizeResult($result, $duration, $timeout);
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            // Log the real cause internally; the response stays generic on purpose.
            $this->logger?->error('Health check "{check}" threw an exception', [
                'check' => $this->getName(),
                'duration' => round($duration, 3),
                'exception' => $e,
            ]);

            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::UNHEALTHY,
                message: 'Health check failed',
                duration: $duration,
                metadata: []
            );
        } finally {
            if (null !== $previousTimeout) {
                $this->applyTimeLimit($previousTimeout);
            }
        }
    }

    /**
     * Attach the measured duration to a result and flag timeout overruns.
     *
     * set_time_limit() does not interrupt time spent in blocking I/O — a
     * stalled socket read can outlive the limit entirely — so the declared
     * timeout cannot be enforced preemptively. Measuring afterwards is what
     * makes an overrun visible: a check that succeeded but took longer than
     * it promised is reported as degraded rather than silently healthy.
     */
    private function finalizeResult(HealthCheckResult $result, float $duration, int $timeout): HealthCheckResult
    {
        $status = $result->status;
        $message = $result->message;

        if ($timeout > 0 && $duration > $timeout && !$status->isUnhealthy()) {
            $this->logger?->warning('Health check "{check}" exceeded its timeout', [
                'check' => $this->getName(),
                'duration' => round($duration, 3),
                'timeout' => $timeout,
            ]);

            $status = HealthCheckStatus::DEGRADED;
            $message = $result->message.' (exceeded timeout)';
        }

        return new HealthCheckResult(
            name: $result->name,
            status: $status,
            message: $message,
            duration: $duration,
            metadata: $result->metadata
        );
    }

    /**
     * Set the PHP time limit, returning the previous value for restoration.
     *
     * Returns null when the limit could not be changed — set_time_limit() is
     * commonly disabled via disable_functions, and it is a no-op in CLI where
     * max_execution_time is already unlimited. In that case the caller skips
     * restoration instead of emitting a second failing call.
     */
    private function applyTimeLimit(int $seconds): ?int
    {
        if ($seconds < 0 || !function_exists('set_time_limit')) {
            return null;
        }

        $previous = ini_get('max_execution_time');

        if (!@set_time_limit($seconds)) {
            return null;
        }

        return false !== $previous ? (int) $previous : 0;
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
