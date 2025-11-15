<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller providing lightweight ping endpoint for liveness probes.
 *
 * This endpoint performs no external service checks and always returns 200,
 * making it ideal for Kubernetes liveness probes and load balancer health checks.
 */
class PingController extends AbstractController
{
    /**
     * Simple ping endpoint to verify the application is running.
     *
     * Returns a simple JSON response with status "up" and current timestamp.
     * Does not perform any database or external service checks.
     *
     * @return JsonResponse JSON response with "up" status
     */
    #[Route('/ping', name: 'health_ping', methods: ['GET'])]
    public function ping(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'up',
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ], 200, [
            'X-Robots-Tag' => 'noindex, nofollow',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        ]);
    }
}
