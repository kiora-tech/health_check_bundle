<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface that all health checks must implement.
 *
 * All classes implementing this interface are automatically tagged
 * with 'health_check.checker' thanks to the AutoconfigureTag attribute.
 */
#[AutoconfigureTag('health_check.checker')]
interface HealthCheckInterface
{
    /**
     * Execute the health check and return the result.
     *
     * This method should be idempotent and should not modify system state.
     * It should complete within the timeout specified by getTimeout().
     */
    public function check(): HealthCheckResult;

    /**
     * Get the unique name for this health check.
     *
     * This name is used to identify the check in results and logs.
     * Should use lowercase with underscores (e.g., 'database', 'redis_cache').
     */
    public function getName(): string;

    /**
     * Get the maximum execution time in seconds for this check.
     *
     * If the check exceeds this timeout, it will be terminated and
     * marked as unhealthy.
     *
     * @return int Timeout in seconds
     */
    public function getTimeout(): int;

    /**
     * Determine if this check is critical to overall system health.
     *
     * If a critical check fails, the overall health status will be
     * marked as unhealthy. Non-critical checks can fail without affecting
     * the overall status.
     *
     * @return bool True if the check is critical, false otherwise
     */
    public function isCritical(): bool;

    /**
     * Get the groups/contexts this check belongs to.
     *
     * Groups allow filtering checks by context (e.g., 'web', 'worker', 'console').
     * A check can belong to multiple groups.
     *
     * @return string[] Array of group names
     */
    public function getGroups(): array;
}
