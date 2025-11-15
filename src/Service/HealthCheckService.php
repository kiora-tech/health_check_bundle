<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Service;

use Kiora\HealthCheckBundle\HealthCheck\HealthCheckInterface;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;

/**
 * Service that aggregates and executes all registered health checks.
 *
 * Health checks are automatically injected via the tagged_iterator pattern
 * in the service container configuration.
 */
class HealthCheckService
{
    /**
     * @param iterable<HealthCheckInterface> $healthChecks
     */
    public function __construct(
        private readonly iterable $healthChecks
    ) {
    }

    /**
     * Execute all registered health checks, optionally filtered by group.
     *
     * @param string|null $group Optional group filter (e.g., 'web', 'worker', 'console')
     *
     * @return array{status: string, timestamp: string, duration: float, checks: array<int, array<string, mixed>>}
     */
    public function runAllChecks(?string $group = null): array
    {
        $startTime = microtime(true);
        $results = [];
        $overallStatus = HealthCheckStatus::HEALTHY;

        foreach ($this->healthChecks as $healthCheck) {
            // Filter by group if specified
            if (null !== $group && !$this->checkBelongsToGroup($healthCheck, $group)) {
                continue;
            }

            $result = $healthCheck->check();
            $results[] = $result;

            // Determine overall status: if any critical check fails, mark as unhealthy
            if ($result->isUnhealthy() && $healthCheck->isCritical()) {
                $overallStatus = HealthCheckStatus::UNHEALTHY;
            }
        }

        $totalDuration = microtime(true) - $startTime;

        return [
            'status' => $overallStatus->value,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'duration' => round($totalDuration, 3),
            'checks' => array_map(
                static fn (HealthCheckResult $result): array => $result->toArray(),
                $results
            ),
        ];
    }

    /**
     * Determine if a health check belongs to a specific group.
     *
     * A check belongs to a group if:
     * - Its groups array is empty (belongs to all groups), OR
     * - The specified group is in its groups array
     */
    private function checkBelongsToGroup(HealthCheckInterface $healthCheck, string $group): bool
    {
        $groups = $healthCheck->getGroups();

        // Empty groups array means the check belongs to all groups
        if ([] === $groups) {
            return true;
        }

        return in_array($group, $groups, true);
    }

    /**
     * Get the overall health status.
     *
     * Returns the appropriate HTTP status code based on health check results.
     */
    public function getHealthStatus(): HealthCheckStatus
    {
        foreach ($this->healthChecks as $healthCheck) {
            $result = $healthCheck->check();

            if ($result->isUnhealthy() && $healthCheck->isCritical()) {
                return HealthCheckStatus::UNHEALTHY;
            }
        }

        return HealthCheckStatus::HEALTHY;
    }

    /**
     * Execute a specific health check by name.
     *
     * @return HealthCheckResult|null Null if check not found
     */
    public function runCheck(string $name): ?HealthCheckResult
    {
        foreach ($this->healthChecks as $healthCheck) {
            if ($healthCheck->getName() === $name) {
                return $healthCheck->check();
            }
        }

        return null;
    }
}
