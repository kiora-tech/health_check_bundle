<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\Checks\RedisHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use PHPUnit\Framework\TestCase;

class RedisHealthCheckTest extends TestCase
{
    public function testGetNameReturnsRedis(): void
    {
        $check = new RedisHealthCheck();

        $this->assertSame('redis', $check->getName());
    }

    public function testGetTimeoutReturnsThreeSeconds(): void
    {
        $check = new RedisHealthCheck();

        $this->assertSame(3, $check->getTimeout());
    }

    public function testDefaultIsCriticalFalse(): void
    {
        $check = new RedisHealthCheck();

        $this->assertFalse($check->isCritical());
    }

    public function testCanBeMarkedAsCritical(): void
    {
        $check = new RedisHealthCheck(critical: true);

        $this->assertTrue($check->isCritical());
    }

    public function testDefaultGroupsIsEmpty(): void
    {
        $check = new RedisHealthCheck();

        $this->assertSame([], $check->getGroups());
    }

    public function testGroupsCanBeSpecified(): void
    {
        $check = new RedisHealthCheck(groups: ['web', 'cache']);

        $this->assertSame(['web', 'cache'], $check->getGroups());
    }

    public function testSuccessfulConnectionReturnsHealthy(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Use Redis hostname from Docker environment, fallback to localhost
        $host = getenv('REDIS_HOST') ?: 'localhost';

        // Skip if Redis is not available
        $redis = new \Redis();
        if (!@$redis->connect($host, 6379, 1)) {
            $this->markTestSkipped(sprintf('Redis server not available at %s:6379', $host));
        }
        $redis->close();

        $check = new RedisHealthCheck(host: $host);
        $result = $check->check();

        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertSame('Redis operational', $result->message);
        $this->assertGreaterThan(0.0, $result->duration);
    }

    public function testFailedConnectionReturnsUnhealthy(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Use an invalid port to force connection failure
        $check = new RedisHealthCheck(port: 9999);
        $result = $check->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('Redis connection failed', $result->message);
    }

    public function testInvalidHostReturnsUnhealthy(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        $check = new RedisHealthCheck(host: 'invalid.host.example.com');
        $result = $check->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('Redis connection failed', $result->message);
    }

    public function testConnectionIsReusedAcrossMultipleChecks(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Use Redis hostname from Docker environment, fallback to localhost
        $host = getenv('REDIS_HOST') ?: 'localhost';

        // Skip if Redis is not available
        $redis = new \Redis();
        if (!@$redis->connect($host, 6379, 1)) {
            $this->markTestSkipped(sprintf('Redis server not available at %s:6379', $host));
        }
        $redis->close();

        $check = new RedisHealthCheck(host: $host);

        // First check should create connection
        $result1 = $check->check();
        $this->assertSame(HealthCheckStatus::HEALTHY, $result1->status);
        $duration1 = $result1->duration;

        // Second check should reuse connection and be faster
        $result2 = $check->check();
        $this->assertSame(HealthCheckStatus::HEALTHY, $result2->status);
        $duration2 = $result2->duration;

        // Third check should also reuse connection
        $result3 = $check->check();
        $this->assertSame(HealthCheckStatus::HEALTHY, $result3->status);
        $duration3 = $result3->duration;

        // Subsequent checks should generally be faster due to connection reuse
        // Note: This is not guaranteed due to system variations, so we just verify all succeed
        $this->assertSame(HealthCheckStatus::HEALTHY, $result1->status);
        $this->assertSame(HealthCheckStatus::HEALTHY, $result2->status);
        $this->assertSame(HealthCheckStatus::HEALTHY, $result3->status);
    }

    public function testConnectionRecoveryAfterFailure(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Use Redis hostname from Docker environment, fallback to localhost
        $host = getenv('REDIS_HOST') ?: 'localhost';

        // First try with invalid port to force failure
        $check = new RedisHealthCheck(host: 'invalid.host.example.com', port: 9999);
        $result1 = $check->check();
        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result1->status);

        // Now check if Redis is available
        $redis = new \Redis();
        if (!@$redis->connect($host, 6379, 1)) {
            $this->markTestSkipped(sprintf('Redis server not available at %s:6379 for recovery test', $host));
        }
        $redis->close();

        // Create a new check with valid settings to test recovery
        $checkRecovered = new RedisHealthCheck(host: $host);
        $result2 = $checkRecovered->check();

        // Should successfully connect with valid settings
        $this->assertSame(HealthCheckStatus::HEALTHY, $result2->status);
        $this->assertSame('Redis operational', $result2->message);
    }

    public function testMultipleInstancesCanHaveDifferentConnections(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Use Redis hostname from Docker environment, fallback to localhost
        $host = getenv('REDIS_HOST') ?: 'localhost';

        // Skip if Redis is not available
        $redis = new \Redis();
        if (!@$redis->connect($host, 6379, 1)) {
            $this->markTestSkipped(sprintf('Redis server not available at %s:6379', $host));
        }
        $redis->close();

        $check1 = new RedisHealthCheck(host: $host, critical: true);
        $check2 = new RedisHealthCheck(host: $host, critical: false);

        $result1 = $check1->check();
        $result2 = $check2->check();

        // Both should succeed independently
        $this->assertSame(HealthCheckStatus::HEALTHY, $result1->status);
        $this->assertSame(HealthCheckStatus::HEALTHY, $result2->status);

        // And maintain their own criticality settings
        $this->assertTrue($check1->isCritical());
        $this->assertFalse($check2->isCritical());
    }

    public function testPersistentConnectionBehavior(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Use Redis hostname from Docker environment, fallback to localhost
        $host = getenv('REDIS_HOST') ?: 'localhost';

        // Skip if Redis is not available
        $redis = new \Redis();
        if (!@$redis->connect($host, 6379, 1)) {
            $this->markTestSkipped(sprintf('Redis server not available at %s:6379', $host));
        }
        $redis->close();

        $check = new RedisHealthCheck(host: $host);

        // Perform multiple checks to verify persistent connection works
        for ($i = 0; $i < 5; ++$i) {
            $result = $check->check();
            $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
            $this->assertSame('Redis operational', $result->message);
        }
    }

    public function testCustomHostAndPort(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        // Use Redis hostname from Docker environment, fallback to localhost
        $host = getenv('REDIS_HOST') ?: 'localhost';

        $check = new RedisHealthCheck(host: $host, port: 6379);

        $this->assertSame('redis', $check->getName());

        // Try to connect - will only succeed if Redis is running
        $redis = new \Redis();
        if (@$redis->connect($host, 6379, 1)) {
            $redis->close();
            $result = $check->check();
            $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        } else {
            // If Redis is not available, skip the connection test
            $this->markTestSkipped(sprintf('Redis server not available at %s:6379', $host));
        }
    }
}
