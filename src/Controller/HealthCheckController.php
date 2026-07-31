<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Controller;

use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use Kiora\HealthCheckBundle\Service\HealthCheckService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller providing health check endpoints.
 *
 * Exposes a RESTful JSON API for monitoring application health status.
 */
class HealthCheckController extends AbstractController
{
    /**
     * Group probed by the readiness endpoint.
     */
    private const READINESS_GROUP = 'readiness';

    /**
     * Headers applied to every health response.
     *
     * Health output must never be cached by proxies or indexed by crawlers:
     * a cached probe would report a state the application has already left.
     *
     * @var array<string, string>
     */
    private const RESPONSE_HEADERS = [
        'X-Robots-Tag' => 'noindex, nofollow',
        'X-Content-Type-Options' => 'nosniff',
        'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
    ];

    public function __construct(
        private readonly HealthCheckService $healthCheckService
    ) {
    }

    /**
     * Get the overall health status of the application.
     *
     * Returns HTTP 200 if healthy or degraded, 503 if unhealthy.
     * Supports optional ?group= query parameter to filter checks by group.
     *
     * @return JsonResponse JSON response with health check results
     */
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(Request $request): JsonResponse
    {
        $results = $this->healthCheckService->runAllChecks($this->resolveGroup($request));

        return $this->respond($results);
    }

    /**
     * Kubernetes readiness probe endpoint.
     *
     * Returns HTTP 200 if the application is ready to serve traffic (all critical dependencies are healthy).
     * Returns HTTP 503 if the application is not ready (one or more critical dependencies are unhealthy).
     *
     * This endpoint checks only health checks in the "readiness" group, allowing you to distinguish
     * between liveness (is the app running?) and readiness (can the app serve traffic?).
     *
     * @return JsonResponse JSON response with readiness check results
     */
    #[Route('/ready', name: 'health_readiness', methods: ['GET'])]
    public function readiness(?Request $request = null): JsonResponse
    {
        $results = $this->healthCheckService->runAllChecks(self::READINESS_GROUP);

        return $this->respond($results);
    }

    /**
     * Build the JSON response, deriving the HTTP code from the reported status.
     *
     * HealthCheckStatus is the single source of truth for the mapping, so a
     * degraded application answers 200 instead of being pulled out of the load
     * balancer rotation for a non-critical failure.
     *
     * @param array{status: string, timestamp: string, duration: float, checks: array<int, array<string, mixed>>, statistics: array<string, mixed>} $results
     */
    private function respond(array $results): JsonResponse
    {
        $statusCode = HealthCheckStatus::tryFrom($results['status'])?->getHttpStatusCode()
            ?? HealthCheckStatus::UNHEALTHY->getHttpStatusCode();

        return new JsonResponse($results, $statusCode, self::RESPONSE_HEADERS);
    }

    /**
     * Read the optional group filter from the query string.
     *
     * Reads through all() so that a repeated or array-shaped parameter
     * (?group[]=web) yields null instead of raising a bad-request exception:
     * a malformed probe URL should not make the endpoint itself fail.
     */
    private function resolveGroup(Request $request): ?string
    {
        $group = $request->query->all()['group'] ?? null;

        return is_string($group) && '' !== $group ? $group : null;
    }
}
