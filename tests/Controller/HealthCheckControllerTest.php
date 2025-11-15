<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\Controller;

use Kiora\HealthCheckBundle\Controller\HealthCheckController;
use Kiora\HealthCheckBundle\Service\HealthCheckService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class HealthCheckControllerTest extends TestCase
{
    public function testCheckReturns200WhenHealthy(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('runAllChecks')->willReturn([
            'status' => 'healthy',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'duration' => 0.123,
            'checks' => [],
        ]);

        $controller = new HealthCheckController($service);
        $request = new Request();
        $response = $controller->check($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testCheckReturns503WhenUnhealthy(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('runAllChecks')->willReturn([
            'status' => 'unhealthy',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'duration' => 0.123,
            'checks' => [],
        ]);

        $controller = new HealthCheckController($service);
        $request = new Request();
        $response = $controller->check($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testCheckWithoutGroupParameterCallsServiceWithNull(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->expects($this->once())
            ->method('runAllChecks')
            ->with(null)
            ->willReturn([
                'status' => 'healthy',
                'timestamp' => '2024-01-01T00:00:00+00:00',
                'duration' => 0.123,
                'checks' => [],
            ]);

        $controller = new HealthCheckController($service);
        $request = new Request();
        $controller->check($request);
    }

    public function testCheckWithGroupParameterPassesGroupToService(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->expects($this->once())
            ->method('runAllChecks')
            ->with('web')
            ->willReturn([
                'status' => 'healthy',
                'timestamp' => '2024-01-01T00:00:00+00:00',
                'duration' => 0.123,
                'checks' => [],
            ]);

        $controller = new HealthCheckController($service);
        $request = new Request(['group' => 'web']);
        $controller->check($request);
    }

    public function testCheckWithDifferentGroupValues(): void
    {
        $groups = ['web', 'worker', 'console', 'custom-group'];

        foreach ($groups as $group) {
            $service = $this->createMock(HealthCheckService::class);
            $service->expects($this->once())
                ->method('runAllChecks')
                ->with($group)
                ->willReturn([
                    'status' => 'healthy',
                    'timestamp' => '2024-01-01T00:00:00+00:00',
                    'duration' => 0.123,
                    'checks' => [],
                ]);

            $controller = new HealthCheckController($service);
            $request = new Request(['group' => $group]);
            $controller->check($request);
        }
    }

    public function testCheckReturnsSecurityHeaders(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('runAllChecks')->willReturn([
            'status' => 'healthy',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'duration' => 0.123,
            'checks' => [],
        ]);

        $controller = new HealthCheckController($service);
        $request = new Request();
        $response = $controller->check($request);

        $this->assertEquals('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    public function testCheckReturnsValidJsonStructure(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('runAllChecks')->willReturn([
            'status' => 'healthy',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'duration' => 0.123,
            'checks' => [
                [
                    'name' => 'database',
                    'status' => 'healthy',
                    'message' => 'Database operational',
                    'duration' => 0.05,
                    'metadata' => [],
                ],
            ],
        ]);

        $controller = new HealthCheckController($service);
        $request = new Request();
        $response = $controller->check($request);

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('duration', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertSame('healthy', $data['status']);
    }
}
