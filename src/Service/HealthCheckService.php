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
     * Cache TTL in seconds to prevent duplicate health check executions.
     */
    private const CACHE_TTL = 1;

    /**
     * @var array<int, HealthCheckResult>|null
     */
    private ?array $cachedResults = null;

    private ?float $cacheTimestamp = null;

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
     * @param string|null $group    Optional group filter (e.g., 'web', 'worker', 'console')
     * @param bool        $useCache Whether to use cached results if available (default: true)
     *
     * @return array{status: string, timestamp: string, duration: float, checks: array<int, array<string, mixed>>, statistics: array<string, mixed>}
     */
    public function runAllChecks(?string $group = null, bool $useCache = true): array
    {
        $startTime = microtime(true);

        // Check if cache is fresh and should be used
        if ($useCache && $this->isCacheFresh() && null === $group && null !== $this->cachedResults) {
            $results = $this->cachedResults;
        } else {
            $results = [];

            foreach ($this->healthChecks as $healthCheck) {
                // Filter by group if specified
                if (null !== $group && !$this->checkBelongsToGroup($healthCheck, $group)) {
                    continue;
                }

                $result = $healthCheck->check();
                $results[] = $result;
            }

            // Cache results only when no group filter is applied
            if (null === $group) {
                $this->cachedResults = $results;
                $this->cacheTimestamp = microtime(true);
            }
        }

        // Recalculate overall status from results
        $overallStatus = HealthCheckStatus::HEALTHY;
        $healthCheckArray = iterator_to_array($this->healthChecks);
        foreach ($results as $index => $result) {
            $healthCheck = $healthCheckArray[$index] ?? null;
            if (null !== $healthCheck && $result->isUnhealthy() && $healthCheck->isCritical()) {
                $overallStatus = HealthCheckStatus::UNHEALTHY;

                break;
            }
        }

        $totalDuration = microtime(true) - $startTime;

        // Calculate statistics
        $statistics = $this->calculateStatistics($results);

        return [
            'status' => $overallStatus->value,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'duration' => round($totalDuration, 3),
            'checks' => array_map(
                static fn (HealthCheckResult $result): array => $result->toArray(),
                $results
            ),
            'statistics' => $statistics,
        ];
    }

    /**
     * Calculate performance statistics from health check results.
     *
     * @param array<int, HealthCheckResult> $results
     *
     * @return array{total_checks: int, slow_checks: int, average_duration: float, slowest_check: array{name: string, duration: float}|null}
     */
    private function calculateStatistics(array $results): array
    {
        $totalChecks = count($results);

        // Identify slow checks (execution time > 1 second)
        $slowChecks = array_filter(
            $results,
            static fn (HealthCheckResult $result): bool => $result->duration > 1.0
        );

        // Find the slowest check
        $sortedByDuration = $results;
        usort($sortedByDuration, static fn (HealthCheckResult $a, HealthCheckResult $b): int => $b->duration <=> $a->duration);
        $slowest = $sortedByDuration[0] ?? null;

        // Calculate average duration
        $averageDuration = $totalChecks > 0
            ? array_sum(array_map(static fn (HealthCheckResult $r): float => $r->duration, $results)) / $totalChecks
            : 0.0;

        return [
            'total_checks' => $totalChecks,
            'slow_checks' => count($slowChecks),
            'average_duration' => round($averageDuration, 3),
            'slowest_check' => null !== $slowest ? [
                'name' => $slowest->name,
                'duration' => round($slowest->duration, 3),
            ] : null,
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
     * Uses cached results if available to avoid re-executing checks.
     *
     * @param bool $useCache Whether to use cached results if available (default: true)
     */
    public function getHealthStatus(bool $useCache = true): HealthCheckStatus
    {
        // Use cached results if available and fresh
        if ($useCache && $this->isCacheFresh() && null !== $this->cachedResults) {
            $healthCheckArray = iterator_to_array($this->healthChecks);
            foreach ($this->cachedResults as $index => $result) {
                $healthCheck = $healthCheckArray[$index] ?? null;
                if (null !== $healthCheck && $result->isUnhealthy() && $healthCheck->isCritical()) {
                    return HealthCheckStatus::UNHEALTHY;
                }
            }

            return HealthCheckStatus::HEALTHY;
        }

        // Execute checks if cache is not available
        foreach ($this->healthChecks as $healthCheck) {
            $result = $healthCheck->check();

            if ($result->isUnhealthy() && $healthCheck->isCritical()) {
                return HealthCheckStatus::UNHEALTHY;
            }
        }

        return HealthCheckStatus::HEALTHY;
    }

    /**
     * Check if the cache is still fresh (within TTL).
     */
    private function isCacheFresh(): bool
    {
        if (null === $this->cachedResults || null === $this->cacheTimestamp) {
            return false;
        }

        return (microtime(true) - $this->cacheTimestamp) < self::CACHE_TTL;
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
