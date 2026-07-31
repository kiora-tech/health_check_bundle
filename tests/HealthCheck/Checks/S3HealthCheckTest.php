<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\HealthCheck\Checks;

use Kiora\HealthCheckBundle\HealthCheck\Checks\S3HealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckStatus;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use PHPUnit\Framework\TestCase;

class S3HealthCheckTest extends TestCase
{
    public function testHealthyWhenBucketIsListable(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(
            new DirectoryListing($this->attributes(3))
        );

        $result = (new S3HealthCheck($filesystem))->check();

        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertSame('S3 storage operational', $result->message);
    }

    public function testHealthyWhenBucketIsEmpty(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([]));

        $result = (new S3HealthCheck($filesystem))->check();

        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
    }

    /**
     * The listing must be consumed lazily.
     *
     * Calling toArray() on a bucket holding millions of objects pages through
     * every one of them, so a probe meant to be cheap becomes an incident of
     * its own. Only the first entry may ever be pulled.
     */
    public function testStopsAfterFirstEntryOnLargeBucket(): void
    {
        // Bounded rather than infinite so a regression fails the assertion
        // instead of hanging the suite.
        $produced = 0;
        $generator = (function () use (&$produced): \Generator {
            for ($i = 0; $i < 1000; ++$i) {
                ++$produced;

                yield $this->attribute('file-'.$i.'.txt');
            }
        })();

        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing($generator));

        $result = (new S3HealthCheck($filesystem))->check();

        $this->assertSame(HealthCheckStatus::HEALTHY, $result->status);
        $this->assertSame(1, $produced, 'The check must not walk the whole bucket');
    }

    public function testUnhealthyWhenFilesystemThrows(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willThrowException(
            new class extends \RuntimeException implements FilesystemException {}
        );

        $result = (new S3HealthCheck($filesystem))->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
        $this->assertSame('S3 storage connection failed', $result->message);
        $this->assertSame([], $result->metadata);
    }

    public function testUnhealthyWhenFilesystemRaisesAnError(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willThrowException(new \Error('driver blew up'));

        $result = (new S3HealthCheck($filesystem))->check();

        $this->assertSame(HealthCheckStatus::UNHEALTHY, $result->status);
    }

    public function testMetadataAndNaming(): void
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing([]));

        $default = new S3HealthCheck($filesystem);
        $named = new S3HealthCheck($filesystem, 'backups', true, ['worker']);

        $this->assertSame('s3', $default->getName());
        $this->assertFalse($default->isCritical());
        $this->assertSame([], $default->getGroups());

        $this->assertSame('backups', $named->getName());
        $this->assertTrue($named->isCritical());
        $this->assertSame(['worker'], $named->getGroups());
    }

    /**
     * @return list<StorageAttributes>
     */
    private function attributes(int $count): array
    {
        $items = [];

        for ($i = 0; $i < $count; ++$i) {
            $items[] = $this->attribute('file-'.$i.'.txt');
        }

        return $items;
    }

    private function attribute(string $path): StorageAttributes
    {
        return new \League\Flysystem\FileAttributes($path);
    }
}
