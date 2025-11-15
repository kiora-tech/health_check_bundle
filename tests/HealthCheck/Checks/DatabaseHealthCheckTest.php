<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\HealthCheck\Checks;

use Doctrine\DBAL\Connection;
use Kiora\HealthCheckBundle\HealthCheck\Checks\DatabaseHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use PHPUnit\Framework\TestCase;

class DatabaseHealthCheckTest extends TestCase
{
    public function testDefaultConnectionNameReturnsDatabaseName(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new DatabaseHealthCheck($connection);

        $this->assertSame('database', $check->getName());
    }

    public function testNamedConnectionReturnsCustomName(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new DatabaseHealthCheck($connection, 'analytics');

        $this->assertSame('database_analytics', $check->getName());
    }

    public function testMultipleConnectionsWithDifferentNames(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $defaultCheck = new DatabaseHealthCheck($connection, 'default');
        $analyticsCheck = new DatabaseHealthCheck($connection, 'analytics');
        $logsCheck = new DatabaseHealthCheck($connection, 'logs');

        $this->assertSame('database', $defaultCheck->getName());
        $this->assertSame('database_analytics', $analyticsCheck->getName());
        $this->assertSame('database_logs', $logsCheck->getName());
    }

    public function testDefaultConnectionIsCritical(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new DatabaseHealthCheck($connection);

        $this->assertTrue($check->isCritical());
    }

    public function testConnectionCanBeNonCritical(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new DatabaseHealthCheck($connection, 'analytics', critical: false);

        $this->assertFalse($check->isCritical());
    }

    public function testDefaultGroupsIsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new DatabaseHealthCheck($connection);

        $this->assertSame([], $check->getGroups());
    }

    public function testGroupsCanBeSpecified(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new DatabaseHealthCheck(
            $connection,
            'default',
            true,
            ['web', 'worker']
        );

        $this->assertSame(['web', 'worker'], $check->getGroups());
    }

    public function testSuccessfulConnectionReturnsHealthy(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn(1);

        $check = new DatabaseHealthCheck($connection);
        $result = $check->check();

        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertSame('Database operational', $result->message);
    }

    public function testFailedConnectionReturnsUnhealthy(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willThrowException(new \Exception('Connection failed'));

        $check = new DatabaseHealthCheck($connection);
        $result = $check->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('Database connection failed', $result->message);
    }
}
