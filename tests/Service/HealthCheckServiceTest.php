<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\Service;

use Kiora\HealthCheckBundle\HealthCheck\HealthCheckInterface;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use Kiora\HealthCheckBundle\Service\HealthCheckService;
use PHPUnit\Framework\TestCase;

class HealthCheckServiceTest extends TestCase
{
    public function testRunAllChecksWithoutGroupFilter(): void
    {
        $check1 = $this->createMockCheck('check1', ['web']);
        $check2 = $this->createMockCheck('check2', ['worker']);
        $check3 = $this->createMockCheck('check3', []); // No groups = belongs to all

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks();

        $this->assertSame('healthy', $results['status']);
        $this->assertCount(3, $results['checks']);
    }

    public function testRunAllChecksFilteredByWebGroup(): void
    {
        $check1 = $this->createMockCheck('check1', ['web']);
        $check2 = $this->createMockCheck('check2', ['worker']);
        $check3 = $this->createMockCheck('check3', []); // No groups = belongs to all

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks('web');

        $this->assertSame('healthy', $results['status']);
        $this->assertCount(2, $results['checks']); // check1 (web) and check3 (all groups)

        $names = array_column($results['checks'], 'name');
        $this->assertContains('check1', $names);
        $this->assertContains('check3', $names);
        $this->assertNotContains('check2', $names);
    }

    public function testRunAllChecksFilteredByWorkerGroup(): void
    {
        $check1 = $this->createMockCheck('check1', ['web']);
        $check2 = $this->createMockCheck('check2', ['worker']);
        $check3 = $this->createMockCheck('check3', []); // No groups = belongs to all

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks('worker');

        $this->assertCount(2, $results['checks']); // check2 (worker) and check3 (all groups)

        $names = array_column($results['checks'], 'name');
        $this->assertContains('check2', $names);
        $this->assertContains('check3', $names);
        $this->assertNotContains('check1', $names);
    }

    public function testRunAllChecksWithMultipleGroupsPerCheck(): void
    {
        $check1 = $this->createMockCheck('check1', ['web', 'worker']);
        $check2 = $this->createMockCheck('check2', ['worker', 'console']);
        $check3 = $this->createMockCheck('check3', ['web']);

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks('worker');

        $this->assertCount(2, $results['checks']); // check1 and check2

        $names = array_column($results['checks'], 'name');
        $this->assertContains('check1', $names);
        $this->assertContains('check2', $names);
        $this->assertNotContains('check3', $names);
    }

    public function testRunAllChecksWithNonExistentGroup(): void
    {
        $check1 = $this->createMockCheck('check1', ['web']);
        $check2 = $this->createMockCheck('check2', ['worker']);
        $check3 = $this->createMockCheck('check3', []); // No groups = belongs to all

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks('nonexistent');

        $this->assertCount(1, $results['checks']); // Only check3 (belongs to all groups)

        $names = array_column($results['checks'], 'name');
        $this->assertContains('check3', $names);
    }

    public function testOverallStatusIsHealthyWhenAllChecksPass(): void
    {
        $check1 = $this->createMockCheck('check1', [], HealthCheckStatus::HEALTHY, true);
        $check2 = $this->createMockCheck('check2', [], HealthCheckStatus::HEALTHY, true);

        $service = new HealthCheckService([$check1, $check2]);
        $results = $service->runAllChecks();

        $this->assertSame('healthy', $results['status']);
    }

    public function testOverallStatusIsUnhealthyWhenCriticalCheckFails(): void
    {
        $check1 = $this->createMockCheck('check1', [], HealthCheckStatus::HEALTHY, true);
        $check2 = $this->createMockCheck('check2', [], HealthCheckStatus::UNHEALTHY, true); // Critical and unhealthy

        $service = new HealthCheckService([$check1, $check2]);
        $results = $service->runAllChecks();

        $this->assertSame('unhealthy', $results['status']);
    }

    public function testOverallStatusIsHealthyWhenNonCriticalCheckFails(): void
    {
        $check1 = $this->createMockCheck('check1', [], HealthCheckStatus::HEALTHY, true);
        $check2 = $this->createMockCheck('check2', [], HealthCheckStatus::UNHEALTHY, false); // Non-critical

        $service = new HealthCheckService([$check1, $check2]);
        $results = $service->runAllChecks();

        $this->assertSame('healthy', $results['status']); // Still healthy because failed check is non-critical
    }

    private function createMockCheck(
        string $name,
        array $groups,
        HealthCheckStatus $status = HealthCheckStatus::HEALTHY,
        bool $critical = true
    ): HealthCheckInterface {
        $result = new HealthCheckResult(
            name: $name,
            status: $status,
            message: 'Test message',
            duration: 0.0,
            metadata: []
        );

        $check = $this->createMock(HealthCheckInterface::class);
        $check->method('getName')->willReturn($name);
        $check->method('getGroups')->willReturn($groups);
        $check->method('isCritical')->willReturn($critical);
        $check->method('check')->willReturn($result);
        $check->method('getTimeout')->willReturn(5);

        return $check;
    }
}
