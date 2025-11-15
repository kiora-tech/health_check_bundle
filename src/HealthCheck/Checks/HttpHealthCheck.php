<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;

/**
 * Health check for HTTP endpoints.
 *
 * Monitors external HTTP endpoints to verify they are accessible
 * and returning expected status codes.
 *
 * Automatically tagged with 'health_check.checker' via interface.
 */
class HttpHealthCheck extends AbstractHealthCheck
{
    /**
     * @param string   $url                 The URL to check
     * @param string   $name                Optional custom name for this check
     * @param int      $timeout             Timeout in seconds
     * @param bool     $critical            Whether this check is critical
     * @param int[]    $expectedStatusCodes Expected HTTP status codes (default: 200, 201, 204)
     * @param string[] $groups              Groups this check belongs to (e.g., ['web', 'worker'])
     */
    public function __construct(
        private readonly string $url,
        private readonly string $name = 'http_endpoint',
        private readonly int $timeout = 5,
        private readonly bool $critical = false,
        private readonly array $expectedStatusCodes = [200, 201, 204],
        private readonly array $groups = []
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
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
            // Create stream context with timeout
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => $this->timeout,
                    'ignore_errors' => true,
                ],
            ]);

            // Perform HTTP request
            $response = @file_get_contents($this->url, false, $context);

            if (false === $response) {
                return new HealthCheckResult(
                    name: $this->getName(),
                    status: HealthCheckStatus::UNHEALTHY,
                    message: 'HTTP endpoint unreachable',
                    duration: 0.0,
                    metadata: []
                );
            }

            // Parse HTTP response code
            $statusCode = 0;
            // @phpstan-ignore-next-line - $http_response_header is a magic variable set by file_get_contents()
            if (count($http_response_header ?? []) > 0) {
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
                $statusCode = (int) ($matches[1] ?? 0);
            }

            // Check if status code is expected
            if (!in_array($statusCode, $this->expectedStatusCodes, true)) {
                return new HealthCheckResult(
                    name: $this->getName(),
                    status: HealthCheckStatus::UNHEALTHY,
                    message: 'HTTP endpoint returned unexpected status',
                    duration: 0.0,
                    metadata: []
                );
            }

            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::HEALTHY,
                message: 'HTTP endpoint operational',
                duration: 0.0,
                metadata: []
            );
        } catch (\Exception $e) {
            return new HealthCheckResult(
                name: $this->getName(),
                status: HealthCheckStatus::UNHEALTHY,
                message: 'HTTP check failed',
                duration: 0.0,
                metadata: []
            );
        }
    }
}
