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
     * Cache key used when no group filter is applied.
     *
     * Prefixed with a NUL byte so it can never collide with a user-defined
     * group name.
     */
    private const CACHE_KEY_ALL = "\0all";

    /**
     * Threshold in seconds above which a check is reported as slow.
     */
    private const SLOW_CHECK_THRESHOLD = 1.0;

    /**
     * Per-group result cache.
     *
     * Keyed by group name (or self::CACHE_KEY_ALL when unfiltered) so that a
     * filtered run never serves results computed for a different scope.
     *
     * @var array<string, array{executions: list<array{result: HealthCheckResult, critical: bool}>, timestamp: float}>
     */
    private array $cache = [];

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
     * @return array{status: string, timestamp: string, duration: float, checks: array<int, array<string, mixed>>, statistics: array{total_checks: int, slow_checks: int, average_duration: float, slowest_check: array{name: string, duration: float}|null}}
     */
    public function runAllChecks(?string $group = null, bool $useCache = true): array
    {
        $startTime = microtime(true);

        $executions = $this->getExecutions($group, $useCache);

        $results = array_map(
            static fn (array $execution): HealthCheckResult => $execution['result'],
            $executions
        );

        $totalDuration = microtime(true) - $startTime;

        return [
            'status' => $this->resolveOverallStatus($executions)->value,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'duration' => round($totalDuration, 3),
            'checks' => array_map(
                static fn (HealthCheckResult $result): array => $result->toArray(),
                $results
            ),
            'statistics' => $this->calculateStatistics($results),
        ];
    }

    /**
     * Get the overall health status.
     *
     * Returns the status used to derive the HTTP response code.
     * Uses cached results if available to avoid re-executing checks.
     *
     * @param bool        $useCache Whether to use cached results if available (default: true)
     * @param string|null $group    Optional group filter, matching runAllChecks()
     */
    public function getHealthStatus(bool $useCache = true, ?string $group = null): HealthCheckStatus
    {
        return $this->resolveOverallStatus($this->getExecutions($group, $useCache));
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

    /**
     * Get check executions for a group, served from cache when still fresh.
     *
     * Each execution pairs a result with the criticality of the check that
     * produced it. Capturing both together is what keeps criticality correct:
     * correlating results back to checks by position breaks as soon as a group
     * filter skips one, which would attribute a failure to the wrong check.
     *
     * @return list<array{result: HealthCheckResult, critical: bool}>
     */
    private function getExecutions(?string $group, bool $useCache): array
    {
        $cacheKey = $group ?? self::CACHE_KEY_ALL;

        if ($useCache && $this->isCacheFresh($cacheKey)) {
            return $this->cache[$cacheKey]['executions'];
        }

        $executions = [];

        foreach ($this->healthChecks as $healthCheck) {
            // Filter by group if specified
            if (null !== $group && !$this->checkBelongsToGroup($healthCheck, $group)) {
                continue;
            }

            $executions[] = [
                'result' => $healthCheck->check(),
                'critical' => $healthCheck->isCritical(),
            ];
        }

        $this->cache[$cacheKey] = [
            'executions' => $executions,
            'timestamp' => microtime(true),
        ];

        return $executions;
    }

    /**
     * Determine the overall status from a set of executions.
     *
     * A failing critical check makes the whole application unhealthy (503).
     * Anything else that is not fully operational — a degraded check, or a
     * non-critical check that failed — is reported as degraded (200), so the
     * condition stays visible to monitoring without pulling the instance out
     * of the load balancer rotation.
     *
     * @param list<array{result: HealthCheckResult, critical: bool}> $executions
     */
    private function resolveOverallStatus(array $executions): HealthCheckStatus
    {
        $status = HealthCheckStatus::HEALTHY;

        foreach ($executions as $execution) {
            $result = $execution['result'];

            if ($result->isUnhealthy() && $execution['critical']) {
                return HealthCheckStatus::UNHEALTHY;
            }

            if ($result->isUnhealthy() || $result->status->isDegraded()) {
                $status = HealthCheckStatus::DEGRADED;
            }
        }

        return $status;
    }

    /**
     * Calculate performance statistics from health check results.
     *
     * @param list<HealthCheckResult> $results
     *
     * @return array{total_checks: int, slow_checks: int, average_duration: float, slowest_check: array{name: string, duration: float}|null}
     */
    private function calculateStatistics(array $results): array
    {
        $totalChecks = count($results);

        // Identify slow checks
        $slowChecks = array_filter(
            $results,
            static fn (HealthCheckResult $result): bool => $result->duration > self::SLOW_CHECK_THRESHOLD
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
     * Check if the cache for a given key is still fresh (within TTL).
     */
    private function isCacheFresh(string $cacheKey): bool
    {
        if (!isset($this->cache[$cacheKey])) {
            return false;
        }

        return (microtime(true) - $this->cache[$cacheKey]['timestamp']) < self::CACHE_TTL;
    }
}
