<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\Checks\HttpHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class HttpHealthCheckTest extends TestCase
{
    public function testHealthyOnExpectedStatusCode(): void
    {
        $check = new HttpHealthCheck(
            url: 'https://example.test/health',
            httpClient: $this->clientReturning(200)
        );

        $result = $check->check();

        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertSame('HTTP endpoint operational', $result->message);
    }

    public function testUnhealthyOnUnexpectedStatusCode(): void
    {
        $check = new HttpHealthCheck(
            url: 'https://example.test/health',
            httpClient: $this->clientReturning(500)
        );

        $result = $check->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('HTTP endpoint returned unexpected status', $result->message);
    }

    public function testCustomExpectedStatusCodesAreHonoured(): void
    {
        $check = new HttpHealthCheck(
            url: 'https://example.test/health',
            expectedStatusCodes: [418],
            httpClient: $this->clientReturning(418)
        );

        $this->assertSame(HealthCheckStatus::HEALTHY, $check->check()->status);
    }

    public function testUnhealthyWhenTransportFails(): void
    {
        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willThrowException(
            new class extends \RuntimeException implements TransportExceptionInterface {}
        );

        $check = new HttpHealthCheck(url: 'https://example.test/health', httpClient: $client);
        $result = $check->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('HTTP endpoint unreachable', $result->message);
        $this->assertSame([], $result->metadata);
    }

    /**
     * The body must never be downloaded: only the status line matters, and a
     * monitored endpoint may return an arbitrarily large payload.
     */
    public function testResponseBodyIsNeverRead(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->expects($this->never())->method('getContent');

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        $check = new HttpHealthCheck(url: 'https://example.test/health', httpClient: $client);

        $this->assertSame(HealthCheckStatus::HEALTHY, $check->check()->status);
    }

    public function testTimeoutAndRedirectOptionsArePassedToTheClient(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $client = $this->createMock(HttpClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://example.test/health',
                $this->callback(static function (array $options): bool {
                    return 2.0 === $options['timeout'] && 5 === $options['max_redirects'];
                })
            )
            ->willReturn($response);

        $check = new HttpHealthCheck(
            url: 'https://example.test/health',
            timeout: 2,
            httpClient: $client,
            maxRedirects: 5
        );

        $check->check();
    }

    public function testUnreachableHostWithoutHttpClient(): void
    {
        // Falls back to the stream wrapper; an unroutable address fails fast.
        $check = new HttpHealthCheck(
            url: 'http://127.0.0.1:1/health',
            timeout: 1
        );

        $result = $check->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame([], $result->metadata);
    }

    public function testAccessors(): void
    {
        $check = new HttpHealthCheck(
            url: 'https://example.test/health',
            name: 'payments_api',
            timeout: 7,
            critical: true,
            groups: ['web']
        );

        $this->assertSame('payments_api', $check->getName());
        $this->assertSame(7, $check->getTimeout());
        $this->assertTrue($check->isCritical());
        $this->assertSame(['web'], $check->getGroups());
    }

    private function clientReturning(int $statusCode): HttpClientInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('request')->willReturn($response);

        return $client;
    }
}
