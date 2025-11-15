<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Controller;

use Kiora\HealthCheckBundle\Service\HealthCheckService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     *
     * @return JsonResponse JSON response with health check results
     */
    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $results = $this->healthCheckService->runAllChecks();

        $statusCode = 'healthy' === $results['status'] ? 200 : 503;

        return new JsonResponse($results, $statusCode, [
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }
}
