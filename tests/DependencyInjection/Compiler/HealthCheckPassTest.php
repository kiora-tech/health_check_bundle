<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\Tests\DependencyInjection\Compiler;

use Kiora\HealthCheckBundle\DependencyInjection\Compiler\HealthCheckPass;
use Kiora\HealthCheckBundle\HealthCheck\AbstractHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\Checks\DatabaseHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\Checks\RedisHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckResult;
use Kiora\HealthCheckBundle\Service\HealthCheckService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class HealthCheckPassTest extends TestCase
{
    /**
     * The parameter is absent whenever the extension has not run; the pass must
     * not blow up the container build over it.
     */
    public function testDoesNotFailWhenConfigParameterIsMissing(): void
    {
        $container = $this->containerWithService();
        $container->setDefinition('app.custom_check', $this->taggedCheck(CustomCheck::class));

        (new HealthCheckPass())->process($container);

        $this->assertSame(['app.custom_check'], $this->injectedIds($container));
    }

    public function testDoesNothingWithoutTheHealthCheckService(): void
    {
        $container = new ContainerBuilder();

        (new HealthCheckPass())->process($container);

        $this->assertFalse($container->has(HealthCheckService::class));
    }

    /**
     * Checks must stay lazily instantiated. A plain array of references would
     * build every check — and open every connection they hold — up front.
     */
    public function testChecksAreInjectedAsALazyIterator(): void
    {
        $container = $this->containerWithService();
        $container->setDefinition('app.custom_check', $this->taggedCheck(CustomCheck::class));

        (new HealthCheckPass())->process($container);

        $argument = $container->getDefinition(HealthCheckService::class)->getArgument('$healthChecks');

        $this->assertInstanceOf(IteratorArgument::class, $argument);
    }

    public function testDisabledDatabaseCheckIsRemoved(): void
    {
        $container = $this->containerWithService([
            'database' => ['enabled' => false],
            'redis' => ['enabled' => false],
        ]);
        $container->setDefinition('app.database_check', $this->taggedCheck(DatabaseHealthCheck::class));

        (new HealthCheckPass())->process($container);

        $this->assertSame([], $this->injectedIds($container));
        $this->assertFalse($container->hasDefinition('app.database_check'));
    }

    public function testEnabledRedisCheckIsInjected(): void
    {
        $container = $this->containerWithService(['redis' => ['enabled' => true]]);
        $container->setDefinition('app.redis_check', $this->taggedCheck(RedisHealthCheck::class));

        (new HealthCheckPass())->process($container);

        $this->assertSame(['app.redis_check'], $this->injectedIds($container));
    }

    public function testRedisCheckIsDisabledByDefault(): void
    {
        $container = $this->containerWithService(['database' => ['enabled' => true]]);
        $container->setDefinition('app.redis_check', $this->taggedCheck(RedisHealthCheck::class));

        (new HealthCheckPass())->process($container);

        $this->assertSame([], $this->injectedIds($container));
    }

    /**
     * Enable/disable resolution keys off the service class, not its id.
     *
     * Matching on a substring of the id would disable any application service
     * whose name merely contains "DatabaseHealthCheck".
     */
    public function testUnrelatedServiceNamedLikeABuiltInIsNotDisabled(): void
    {
        $container = $this->containerWithService(['database' => ['enabled' => false]]);
        $container->setDefinition('app.my_DatabaseHealthCheck_wrapper', $this->taggedCheck(CustomCheck::class));

        (new HealthCheckPass())->process($container);

        $this->assertSame(['app.my_DatabaseHealthCheck_wrapper'], $this->injectedIds($container));
        $this->assertTrue($container->hasDefinition('app.my_DatabaseHealthCheck_wrapper'));
    }

    public function testChecksAreOrderedByTagPriority(): void
    {
        $container = $this->containerWithService();

        $low = $this->taggedCheck(CustomCheck::class, ['priority' => -10]);
        $high = $this->taggedCheck(CustomCheck::class, ['priority' => 100]);
        $default = $this->taggedCheck(CustomCheck::class);

        $container->setDefinition('app.low', $low);
        $container->setDefinition('app.high', $high);
        $container->setDefinition('app.default', $default);

        (new HealthCheckPass())->process($container);

        $this->assertSame(['app.high', 'app.default', 'app.low'], $this->injectedIds($container));
    }

    /**
     * @param array<string, mixed>|null $checksConfig
     */
    private function containerWithService(?array $checksConfig = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(HealthCheckService::class, new Definition(HealthCheckService::class));

        if (null !== $checksConfig) {
            $container->setParameter('health_check.checks', $checksConfig);
        }

        return $container;
    }

    /**
     * @param array<string, mixed> $tagAttributes
     */
    private function taggedCheck(string $class, array $tagAttributes = []): Definition
    {
        $definition = new Definition($class);
        $definition->addTag(HealthCheckPass::TAG_NAME, $tagAttributes);

        return $definition;
    }

    /**
     * @return list<string>
     */
    private function injectedIds(ContainerBuilder $container): array
    {
        $argument = $container->getDefinition(HealthCheckService::class)->getArgument('$healthChecks');

        $references = $argument instanceof IteratorArgument ? $argument->getValues() : $argument;

        $ids = [];

        foreach ((array) $references as $reference) {
            $this->assertInstanceOf(Reference::class, $reference);
            $ids[] = (string) $reference;
        }

        return $ids;
    }
}

class CustomCheck extends AbstractHealthCheck
{
    public function getName(): string
    {
        return 'custom';
    }

    public function getTimeout(): int
    {
        return 5;
    }

    public function isCritical(): bool
    {
        return false;
    }

    protected function doCheck(): HealthCheckResult
    {
        return $this->createHealthyResult('Custom check passed');
    }
}
