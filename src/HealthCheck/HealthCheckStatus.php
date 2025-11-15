<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck;

/**
 * Health check status enumeration.
 *
 * Represents the possible states of a health check result.
 */
enum HealthCheckStatus: string
{
    case HEALTHY = 'healthy';
    case DEGRADED = 'degraded';
    case UNHEALTHY = 'unhealthy';

    /**
     * Check if the status indicates a healthy state.
     */
    public function isHealthy(): bool
    {
        return self::HEALTHY === $this;
    }

    /**
     * Check if the status indicates an unhealthy state.
     */
    public function isUnhealthy(): bool
    {
        return self::UNHEALTHY === $this;
    }

    /**
     * Check if the status indicates a degraded state.
     */
    public function isDegraded(): bool
    {
        return self::DEGRADED === $this;
    }

    /**
     * Get HTTP status code corresponding to this health status.
     */
    public function getHttpStatusCode(): int
    {
        return match ($this) {
            self::HEALTHY, self::DEGRADED => 200,
            self::UNHEALTHY => 503,
        };
    }
}
