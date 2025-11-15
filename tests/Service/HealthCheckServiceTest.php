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

    public function testCacheHitPreventsDoubleExecution(): void
    {
        $callCount = 0;
        $check = $this->createMock(HealthCheckInterface::class);
        $check->method('getName')->willReturn('test_check');
        $check->method('getGroups')->willReturn([]);
        $check->method('isCritical')->willReturn(true);
        $check->method('getTimeout')->willReturn(5);
        $check->method('check')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;

            return new HealthCheckResult(
                name: 'test_check',
                status: HealthCheckStatus::HEALTHY,
                message: 'Test message',
                duration: 0.0,
                metadata: []
            );
        });

        $service = new HealthCheckService([$check]);

        // First call should execute the check
        $service->runAllChecks();
        $this->assertSame(1, $callCount, 'First call should execute the check');

        // Second call should use cache
        $service->runAllChecks();
        $this->assertSame(1, $callCount, 'Second call should use cached results');

        // getHealthStatus should also use cache
        $service->getHealthStatus();
        $this->assertSame(1, $callCount, 'getHealthStatus should use cached results');
    }

    public function testCacheExpiresAfterTTL(): void
    {
        $callCount = 0;
        $check = $this->createMock(HealthCheckInterface::class);
        $check->method('getName')->willReturn('test_check');
        $check->method('getGroups')->willReturn([]);
        $check->method('isCritical')->willReturn(true);
        $check->method('getTimeout')->willReturn(5);
        $check->method('check')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;

            return new HealthCheckResult(
                name: 'test_check',
                status: HealthCheckStatus::HEALTHY,
                message: 'Test message',
                duration: 0.0,
                metadata: []
            );
        });

        $service = new HealthCheckService([$check]);

        // First call
        $service->runAllChecks();
        $this->assertSame(1, $callCount);

        // Wait for cache to expire (1 second + small buffer)
        usleep(1100000); // 1.1 seconds

        // Should execute again after cache expiration
        $service->runAllChecks();
        $this->assertSame(2, $callCount, 'Check should execute again after cache expiration');
    }

    public function testCacheBypassWithUseCacheFalse(): void
    {
        $callCount = 0;
        $check = $this->createMock(HealthCheckInterface::class);
        $check->method('getName')->willReturn('test_check');
        $check->method('getGroups')->willReturn([]);
        $check->method('isCritical')->willReturn(true);
        $check->method('getTimeout')->willReturn(5);
        $check->method('check')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;

            return new HealthCheckResult(
                name: 'test_check',
                status: HealthCheckStatus::HEALTHY,
                message: 'Test message',
                duration: 0.0,
                metadata: []
            );
        });

        $service = new HealthCheckService([$check]);

        // First call with cache enabled
        $service->runAllChecks(null, true);
        $this->assertSame(1, $callCount);

        // Second call with cache disabled should execute again
        $service->runAllChecks(null, false);
        $this->assertSame(2, $callCount, 'useCache=false should bypass cache');

        // Third call with cache enabled should use fresh cache from second call
        $service->runAllChecks(null, true);
        $this->assertSame(2, $callCount, 'Cache should be available from previous call');
    }

    public function testGetHealthStatusUsesCachedResults(): void
    {
        $callCount = 0;
        $check = $this->createMock(HealthCheckInterface::class);
        $check->method('getName')->willReturn('test_check');
        $check->method('getGroups')->willReturn([]);
        $check->method('isCritical')->willReturn(true);
        $check->method('getTimeout')->willReturn(5);
        $check->method('check')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;

            return new HealthCheckResult(
                name: 'test_check',
                status: HealthCheckStatus::HEALTHY,
                message: 'Test message',
                duration: 0.0,
                metadata: []
            );
        });

        $service = new HealthCheckService([$check]);

        // Populate cache
        $service->runAllChecks();
        $this->assertSame(1, $callCount);

        // getHealthStatus should use cache
        $status = $service->getHealthStatus();
        $this->assertSame(HealthCheckStatus::HEALTHY, $status);
        $this->assertSame(1, $callCount, 'getHealthStatus should not execute checks when cache is available');
    }

    public function testGetHealthStatusBypassesCacheWhenRequested(): void
    {
        $callCount = 0;
        $check = $this->createMock(HealthCheckInterface::class);
        $check->method('getName')->willReturn('test_check');
        $check->method('getGroups')->willReturn([]);
        $check->method('isCritical')->willReturn(true);
        $check->method('getTimeout')->willReturn(5);
        $check->method('check')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;

            return new HealthCheckResult(
                name: 'test_check',
                status: HealthCheckStatus::HEALTHY,
                message: 'Test message',
                duration: 0.0,
                metadata: []
            );
        });

        $service = new HealthCheckService([$check]);

        // Populate cache
        $service->runAllChecks();
        $this->assertSame(1, $callCount);

        // getHealthStatus with useCache=false should execute checks
        $status = $service->getHealthStatus(false);
        $this->assertSame(HealthCheckStatus::HEALTHY, $status);
        $this->assertSame(2, $callCount, 'getHealthStatus with useCache=false should execute checks');
    }

    public function testCacheReturnsCorrectUnhealthyStatus(): void
    {
        $check1 = $this->createMockCheck('healthy_check', [], HealthCheckStatus::HEALTHY, true);
        $check2 = $this->createMockCheck('unhealthy_check', [], HealthCheckStatus::UNHEALTHY, true);

        $service = new HealthCheckService([$check1, $check2]);

        // Populate cache
        $results = $service->runAllChecks();
        $this->assertSame('unhealthy', $results['status']);

        // getHealthStatus should return unhealthy from cache
        $status = $service->getHealthStatus();
        $this->assertSame(HealthCheckStatus::UNHEALTHY, $status);
    }

    public function testGroupFilterDoesNotUseCache(): void
    {
        $callCount = 0;
        $check = $this->createMock(HealthCheckInterface::class);
        $check->method('getName')->willReturn('test_check');
        $check->method('getGroups')->willReturn(['web']);
        $check->method('isCritical')->willReturn(true);
        $check->method('getTimeout')->willReturn(5);
        $check->method('check')->willReturnCallback(function () use (&$callCount) {
            ++$callCount;

            return new HealthCheckResult(
                name: 'test_check',
                status: HealthCheckStatus::HEALTHY,
                message: 'Test message',
                duration: 0.0,
                metadata: []
            );
        });

        $service = new HealthCheckService([$check]);

        // First call without group filter (should cache)
        $service->runAllChecks();
        $this->assertSame(1, $callCount);

        // Call with group filter should not use cache and should execute
        $service->runAllChecks('web');
        $this->assertSame(2, $callCount, 'Group filter should bypass cache');

        // Another call without group should use original cache
        $service->runAllChecks();
        $this->assertSame(2, $callCount, 'Call without group should use cache');
    }

    public function testStatisticsArePresentInResponse(): void
    {
        $check1 = $this->createMockCheckWithDuration('check1', [], 0.05);
        $check2 = $this->createMockCheckWithDuration('check2', [], 0.15);
        $check3 = $this->createMockCheckWithDuration('check3', [], 0.10);

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks();

        $this->assertArrayHasKey('statistics', $results);
        $this->assertIsArray($results['statistics']);
        $this->assertArrayHasKey('total_checks', $results['statistics']);
        $this->assertArrayHasKey('slow_checks', $results['statistics']);
        $this->assertArrayHasKey('average_duration', $results['statistics']);
        $this->assertArrayHasKey('slowest_check', $results['statistics']);
    }

    public function testStatisticsTotalChecksCount(): void
    {
        $check1 = $this->createMockCheckWithDuration('check1', [], 0.05);
        $check2 = $this->createMockCheckWithDuration('check2', [], 0.15);
        $check3 = $this->createMockCheckWithDuration('check3', [], 0.10);

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks();

        $this->assertSame(3, $results['statistics']['total_checks']);
    }

    public function testStatisticsSlowChecksCount(): void
    {
        $check1 = $this->createMockCheckWithDuration('fast_check', [], 0.5);
        $check2 = $this->createMockCheckWithDuration('slow_check_1', [], 1.2);
        $check3 = $this->createMockCheckWithDuration('slow_check_2', [], 2.5);
        $check4 = $this->createMockCheckWithDuration('fast_check_2', [], 0.8);

        $service = new HealthCheckService([$check1, $check2, $check3, $check4]);
        $results = $service->runAllChecks();

        $this->assertSame(2, $results['statistics']['slow_checks'], 'Should identify 2 checks slower than 1 second');
    }

    public function testStatisticsSlowChecksCountWhenNoneAreSlow(): void
    {
        $check1 = $this->createMockCheckWithDuration('check1', [], 0.05);
        $check2 = $this->createMockCheckWithDuration('check2', [], 0.15);
        $check3 = $this->createMockCheckWithDuration('check3', [], 0.50);

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks();

        $this->assertSame(0, $results['statistics']['slow_checks']);
    }

    public function testStatisticsAverageDurationCalculation(): void
    {
        // Durations: 0.1, 0.2, 0.3 => Average: 0.2
        $check1 = $this->createMockCheckWithDuration('check1', [], 0.1);
        $check2 = $this->createMockCheckWithDuration('check2', [], 0.2);
        $check3 = $this->createMockCheckWithDuration('check3', [], 0.3);

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks();

        $this->assertSame(0.2, $results['statistics']['average_duration']);
    }

    public function testStatisticsSlowestCheckIdentification(): void
    {
        $check1 = $this->createMockCheckWithDuration('fast_check', [], 0.05);
        $check2 = $this->createMockCheckWithDuration('slowest_check', [], 0.75);
        $check3 = $this->createMockCheckWithDuration('medium_check', [], 0.25);

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks();

        $this->assertIsArray($results['statistics']['slowest_check']);
        $this->assertSame('slowest_check', $results['statistics']['slowest_check']['name']);
        $this->assertSame(0.75, $results['statistics']['slowest_check']['duration']);
    }

    public function testStatisticsWhenNoChecksExecuted(): void
    {
        $service = new HealthCheckService([]);
        $results = $service->runAllChecks();

        $this->assertSame(0, $results['statistics']['total_checks']);
        $this->assertSame(0, $results['statistics']['slow_checks']);
        $this->assertSame(0.0, $results['statistics']['average_duration']);
        $this->assertNull($results['statistics']['slowest_check']);
    }

    public function testStatisticsWithGroupFiltering(): void
    {
        $check1 = $this->createMockCheckWithDuration('web_check', ['web'], 0.1);
        $check2 = $this->createMockCheckWithDuration('worker_check', ['worker'], 1.5);
        $check3 = $this->createMockCheckWithDuration('all_groups_check', [], 0.3);

        $service = new HealthCheckService([$check1, $check2, $check3]);
        $results = $service->runAllChecks('web');

        // Should only count web_check and all_groups_check
        $this->assertSame(2, $results['statistics']['total_checks']);
        $this->assertSame(0, $results['statistics']['slow_checks']);
        $this->assertSame(0.2, $results['statistics']['average_duration']); // (0.1 + 0.3) / 2
        $this->assertSame('all_groups_check', $results['statistics']['slowest_check']['name']);
        $this->assertSame(0.3, $results['statistics']['slowest_check']['duration']);
    }

    public function testStatisticsDurationRounding(): void
    {
        // Test that durations are rounded to 3 decimal places
        $check1 = $this->createMockCheckWithDuration('check1', [], 0.1234567);
        $check2 = $this->createMockCheckWithDuration('check2', [], 0.9876543);

        $service = new HealthCheckService([$check1, $check2]);
        $results = $service->runAllChecks();

        // Average: (0.1234567 + 0.9876543) / 2 = 0.5555555 => rounded to 0.556
        $this->assertSame(0.556, $results['statistics']['average_duration']);
        $this->assertSame(0.988, $results['statistics']['slowest_check']['duration']);
    }

    public function testStatisticsWithSingleCheck(): void
    {
        $check = $this->createMockCheckWithDuration('single_check', [], 0.42);

        $service = new HealthCheckService([$check]);
        $results = $service->runAllChecks();

        $this->assertSame(1, $results['statistics']['total_checks']);
        $this->assertSame(0, $results['statistics']['slow_checks']);
        $this->assertSame(0.42, $results['statistics']['average_duration']);
        $this->assertSame('single_check', $results['statistics']['slowest_check']['name']);
        $this->assertSame(0.42, $results['statistics']['slowest_check']['duration']);
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

    private function createMockCheckWithDuration(
        string $name,
        array $groups,
        float $duration,
        HealthCheckStatus $status = HealthCheckStatus::HEALTHY,
        bool $critical = true
    ): HealthCheckInterface {
        $result = new HealthCheckResult(
            name: $name,
            status: $status,
            message: 'Test message',
            duration: $duration,
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
