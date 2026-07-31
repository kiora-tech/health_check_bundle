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

        $data = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('duration', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertSame('healthy', $data['status']);
    }

    public function testReadinessReturns200WhenHealthy(): void
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
        $response = $controller->readiness($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReadinessReturns503WhenUnhealthy(): void
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
        $response = $controller->readiness($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(503, $response->getStatusCode());
    }

    public function testReadinessOnlyRunsChecksInReadinessGroup(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->expects($this->once())
            ->method('runAllChecks')
            ->with('readiness')
            ->willReturn([
                'status' => 'healthy',
                'timestamp' => '2024-01-01T00:00:00+00:00',
                'duration' => 0.123,
                'checks' => [],
            ]);

        $controller = new HealthCheckController($service);
        $request = new Request();
        $controller->readiness($request);
    }

    public function testReadinessWithNoChecksInReadinessGroup(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('runAllChecks')->willReturn([
            'status' => 'healthy',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'duration' => 0.001,
            'checks' => [], // No checks in readiness group
        ]);

        $controller = new HealthCheckController($service);
        $request = new Request();
        $response = $controller->readiness($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertEmpty($data['checks']);
        $this->assertSame('healthy', $data['status']);
    }

    public function testReadinessReturnsSecurityHeaders(): void
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
        $response = $controller->readiness($request);

        $this->assertEquals('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    /**
     * A degraded application still serves traffic.
     *
     * Only a critical failure warrants 503; answering 503 for a degraded state
     * would pull the instance out of rotation over a non-critical dependency.
     */
    public function testCheckReturns200WhenDegraded(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('runAllChecks')->willReturn([
            'status' => 'degraded',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'duration' => 0.123,
            'checks' => [],
        ]);

        $controller = new HealthCheckController($service);
        $response = $controller->check(new Request());

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testReadinessReturns200WhenDegraded(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('runAllChecks')->willReturn([
            'status' => 'degraded',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'duration' => 0.123,
            'checks' => [],
        ]);

        $controller = new HealthCheckController($service);
        $response = $controller->readiness(new Request());

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * An unrecognised status must fail closed rather than be treated as healthy.
     */
    public function testUnknownStatusFallsBackTo503(): void
    {
        $service = $this->createMock(HealthCheckService::class);
        $service->method('runAllChecks')->willReturn([
            'status' => 'bogus',
            'timestamp' => '2024-01-01T00:00:00+00:00',
            'duration' => 0.123,
            'checks' => [],
        ]);

        $controller = new HealthCheckController($service);
        $response = $controller->check(new Request());

        $this->assertEquals(503, $response->getStatusCode());
    }

    /**
     * A malformed probe URL (?group[]=web) must not make the endpoint itself
     * fail; the filter is simply ignored.
     */
    public function testArrayShapedGroupParameterIsIgnored(): void
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
        $response = $controller->check(new Request(['group' => ['web', 'worker']]));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testEmptyGroupParameterIsTreatedAsNoFilter(): void
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
        $controller->check(new Request(['group' => '']));
    }

    public function testReadinessReturnsValidJsonStructure(): void
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
            'statistics' => [
                'total_checks' => 1,
                'slow_checks' => 0,
                'average_duration' => 0.05,
                'slowest_check' => [
                    'name' => 'database',
                    'duration' => 0.05,
                ],
            ],
        ]);

        $controller = new HealthCheckController($service);
        $request = new Request();
        $response = $controller->readiness($request);

        $data = json_decode((string) $response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('duration', $data);
        $this->assertArrayHasKey('checks', $data);
        $this->assertSame('healthy', $data['status']);
    }
}
