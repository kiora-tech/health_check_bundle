<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Controller;

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
    public function __construct(
        private readonly HealthCheckService $healthCheckService
    ) {
    }

    /**
     * Get the overall health status of the application.
     *
     * Returns HTTP 200 if healthy, 503 if unhealthy.
     * Supports optional ?group= query parameter to filter checks by group.
     *
     * @return JsonResponse JSON response with health check results
     */
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(Request $request): JsonResponse
    {
        $group = $request->query->get('group');
        $results = $this->healthCheckService->runAllChecks($group);

        $statusCode = 'healthy' === $results['status'] ? 200 : 503;

        return new JsonResponse($results, $statusCode, [
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
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
    public function readiness(Request $request): JsonResponse
    {
        // Only check "readiness" group
        $results = $this->healthCheckService->runAllChecks('readiness');

        $statusCode = 'healthy' === $results['status'] ? 200 : 503;

        return new JsonResponse($results, $statusCode, [
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }
}
