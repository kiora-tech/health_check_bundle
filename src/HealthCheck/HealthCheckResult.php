<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck;

/**
 * Immutable value object representing the result of a health check.
 *
 * Contains all information about a health check execution including
 * status, timing, and optional metadata.
 */
readonly class HealthCheckResult
{
    /**
     * @param string $name Unique name identifying this health check
     * @param HealthCheckStatus $status The health status result
     * @param string $message Human-readable description of the result
     * @param float $duration Execution time in seconds
     * @param array<string, mixed> $metadata Additional contextual information
     */
    public function __construct(
        public string $name,
        public HealthCheckStatus $status,
        public string $message,
        public float $duration,
        public array $metadata = []
    ) {
    }

    /**
     * Check if this result indicates a healthy state.
     */
    public function isHealthy(): bool
    {
        return $this->status->isHealthy();
    }

    /**
     * Check if this result indicates an unhealthy state.
     */
    public function isUnhealthy(): bool
    {
        return $this->status->isUnhealthy();
    }

    /**
     * Convert the result to an array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'status' => $this->status->value,
            'message' => $this->message,
            'duration' => round($this->duration, 3),
            'metadata' => $this->metadata,
        ];
    }
}
