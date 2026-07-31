<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\HealthCheck;

use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerAwareInterface;

/**
 * Covers the failure handling added to AbstractHealthCheck: exception capture,
 * internal logging, and timeout overrun reporting.
 */
class AbstractHealthCheckResilienceTest extends TestCase
{
    public function testThrownExceptionBecomesUnhealthyResult(): void
    {
        $check = new ThrowingHealthCheck(new \RuntimeException('connection refused to db-01:5432'));

        $result = $check->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('Health check failed', $result->message);
    }

    public function testErrorsAreCaughtNotJustExceptions(): void
    {
        $check = new ThrowingHealthCheck(new \Error('Class "Redis" not found'));

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $check->check()->status);
    }

    /**
     * The response must not leak internal detail, but the operator needs it:
     * the exception goes to the log, never to the payload.
     */
    public function testFailureDetailGoesToTheLoggerAndNotThePayload(): void
    {
        $exception = new \RuntimeException('connection refused to db-01:5432');
        $logger = new RecordingLogger();

        $check = new ThrowingHealthCheck($exception);
        $check->setLogger($logger);

        $result = $check->check();

        $this->assertStringNotContainsString('db-01', $result->message);
        $this->assertSame([], $result->metadata);

        $this->assertCount(1, $logger->records);
        $this->assertSame('error', $logger->records[0]['level']);
        $this->assertSame($exception, $logger->records[0]['context']['exception']);
        $this->assertSame('throwing', $logger->records[0]['context']['check']);
    }

    public function testCheckIsLoggerAware(): void
    {
        $this->assertInstanceOf(LoggerAwareInterface::class, new ThrowingHealthCheck(new \Error('x')));
    }

    public function testWorksWithoutALogger(): void
    {
        // No logger injected: the check must still degrade gracefully.
        $result = (new ThrowingHealthCheck(new \RuntimeException('boom')))->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
    }

    /**
     * set_time_limit() cannot interrupt blocking I/O, so an overrun is detected
     * after the fact and surfaced instead of passing as healthy.
     */
    public function testOverrunningCheckIsReportedAsDegraded(): void
    {
        $logger = new RecordingLogger();
        $check = new SlowHealthCheck(timeout: 1, sleepSeconds: 1.1);
        $check->setLogger($logger);

        $result = $check->check();

        $this->assertSame(HealthCheckStatus::DEGRADED, $result->status);
        $this->assertStringContainsString('exceeded timeout', $result->message);
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0]['level']);
    }

    public function testCheckWithinItsTimeoutStaysHealthy(): void
    {
        $result = (new SlowHealthCheck(timeout: 5, sleepSeconds: 0.0))->check();

        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertStringNotContainsString('exceeded timeout', $result->message);
    }

    /**
     * An overrun must not upgrade an already-failing check to degraded, which
     * would turn a 503 into a 200.
     */
    public function testOverrunDoesNotMaskAnUnhealthyResult(): void
    {
        $check = new SlowHealthCheck(timeout: 1, sleepSeconds: 1.1, status: HealthCheckStatus::UNHEALTHY);

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $check->check()->status);
    }

    public function testDurationIsMeasuredByTheBaseClass(): void
    {
        $result = (new SlowHealthCheck(timeout: 5, sleepSeconds: 0.05))->check();

        $this->assertGreaterThan(0.0, $result->duration);
    }
}

class ThrowingHealthCheck extends AbstractHealthCheck
{
    public function __construct(private readonly \Throwable $throwable)
    {
    }

    public function getName(): string
    {
        return 'throwing';
    }

    public function getTimeout(): int
    {
        return 5;
    }

    public function isCritical(): bool
    {
        return true;
    }

    protected function doCheck(): HealthCheckResult
    {
        throw $this->throwable;
    }
}

class SlowHealthCheck extends AbstractHealthCheck
{
    public function __construct(
        private readonly int $timeout,
        private readonly float $sleepSeconds,
        private readonly HealthCheckStatus $status = HealthCheckStatus::HEALTHY
    ) {
    }

    public function getName(): string
    {
        return 'slow';
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function isCritical(): bool
    {
        return false;
    }

    protected function doCheck(): HealthCheckResult
    {
        if ($this->sleepSeconds > 0.0) {
            usleep((int) ($this->sleepSeconds * 1_000_000));
        }

        return HealthCheckStatus::UNHEALTHY === $this->status
            ? $this->createUnhealthyResult('Slow check failed')
            : $this->createHealthyResult('Slow check passed');
    }
}

/**
 * Minimal PSR-3 logger capturing what the checks report.
 */
class RecordingLogger extends AbstractLogger
{
    /**
     * @var list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => is_string($level) ? $level : (string) json_encode($level),
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
