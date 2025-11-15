<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\Controller;

use Kiora\HealthCheckBundle\Controller\PingController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Test suite for PingController.
 */
class PingControllerTest extends TestCase
{
    private PingController $controller;

    protected function setUp(): void
    {
        $this->controller = new PingController();
    }

    public function testPingReturnsUpStatus(): void
    {
        $response = $this->controller->ping();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('up', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testPingReturnsValidTimestamp(): void
    {
        $response = $this->controller->ping();
        $data = json_decode($response->getContent(), true);

        // Verify timestamp is in RFC3339 format
        $this->assertNotEmpty($data['timestamp']);
        $timestamp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::RFC3339, $data['timestamp']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $timestamp);
    }

    public function testPingReturnsSecurityHeaders(): void
    {
        $response = $this->controller->ping();

        $this->assertEquals('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));

        // Verify Cache-Control contains all required directives (order doesn't matter)
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }

    public function testPingDoesNotRequireExternalDependencies(): void
    {
        // The controller should work without any constructor dependencies
        $controller = new PingController();
        $response = $controller->ping();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
