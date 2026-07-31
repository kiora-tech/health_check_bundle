<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Health check for HTTP endpoints.
 *
 * Monitors external HTTP endpoints to verify they are accessible
 * and returning expected status codes.
 *
 * Pass a Symfony HttpClient to $httpClient when available: it handles
 * timeouts, redirects and TLS properly. Without one, the check falls back to
 * a stream wrapper, which requires allow_url_fopen to be enabled.
 *
 * Automatically tagged with 'health_check.checker' via interface.
 */
class HttpHealthCheck extends AbstractHealthCheck
{
    /**
     * Matches the status line of both HTTP/1.x ("HTTP/1.1 200 OK") and
     * HTTP/2 ("HTTP/2 200"), which omits the minor version.
     */
    private const STATUS_LINE_PATTERN = '#^HTTP/\d(?:\.\d)?\s+(\d{3})#i';

    /**
     * @param string                   $url                 The URL to check
     * @param string                   $name                Optional custom name for this check
     * @param int                      $timeout             Timeout in seconds
     * @param bool                     $critical            Whether this check is critical
     * @param int[]                    $expectedStatusCodes Expected HTTP status codes (default: 200, 201, 204)
     * @param string[]                 $groups              Groups this check belongs to (e.g., ['web', 'worker'])
     * @param HttpClientInterface|null $httpClient          Optional Symfony HTTP client (recommended)
     * @param int                      $maxRedirects        Maximum number of redirects to follow
     */
    public function __construct(
        private readonly string $url,
        private readonly string $name = 'http_endpoint',
        private readonly int $timeout = 5,
        private readonly bool $critical = false,
        private readonly array $expectedStatusCodes = [200, 201, 204],
        private readonly array $groups = [],
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly int $maxRedirects = 3
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
            $statusCode = null !== $this->httpClient
                ? $this->fetchStatusWithClient($this->httpClient)
                : $this->fetchStatusWithStream();

            if (null === $statusCode) {
                return $this->createUnhealthyResult('HTTP endpoint unreachable');
            }

            if (!in_array($statusCode, $this->expectedStatusCodes, true)) {
                return $this->createUnhealthyResult('HTTP endpoint returned unexpected status');
            }

            return $this->createHealthyResult('HTTP endpoint operational');
        } catch (\Throwable $e) {
            return $this->createUnhealthyResult('HTTP check failed');
        }
    }

    /**
     * Fetch the status code using a Symfony HTTP client.
     *
     * getStatusCode() resolves as soon as the response headers arrive, so the
     * body is never downloaded.
     *
     * @return int|null Null when the endpoint could not be reached
     */
    private function fetchStatusWithClient(HttpClientInterface $client): ?int
    {
        try {
            return $client->request('GET', $this->url, [
                'timeout' => (float) $this->timeout,
                'max_redirects' => $this->maxRedirects,
            ])->getStatusCode();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fetch the status code using the HTTP stream wrapper.
     *
     * Opens the stream and reads only its metadata instead of calling
     * file_get_contents(): the body of a monitored endpoint can be arbitrarily
     * large, and pulling it into memory on every probe is wasteful. Reading the
     * headers off the handle also avoids the $http_response_header magic
     * variable, whose scoping rules make it easy to read a stale value.
     *
     * @return int|null Null when the endpoint could not be reached
     */
    private function fetchStatusWithStream(): ?int
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'follow_location' => $this->maxRedirects > 0 ? 1 : 0,
                'max_redirects' => max(1, $this->maxRedirects),
                'protocol_version' => 1.1,
                'header' => "Connection: close\r\n",
            ],
        ]);

        $stream = @fopen($this->url, 'r', false, $context);

        if (false === $stream) {
            return null;
        }

        try {
            $metadata = stream_get_meta_data($stream);
        } finally {
            fclose($stream);
        }

        $headers = $metadata['wrapper_data'] ?? [];

        if (!is_array($headers)) {
            return null;
        }

        // With follow_location the wrapper keeps the status line of every hop;
        // the last one is the response actually being judged.
        $statusCode = null;

        foreach ($headers as $header) {
            if (is_string($header) && 1 === preg_match(self::STATUS_LINE_PATTERN, $header, $matches)) {
                $statusCode = (int) $matches[1];
            }
        }

        return $statusCode;
    }
}
