<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\DependencyInjection\Compiler;

use Kiora\HealthCheckBundle\HealthCheck\Checks\DatabaseHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\Checks\RedisHealthCheck;
use Kiora\HealthCheckBundle\Service\HealthCheckService;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to register tagged health check services.
 *
 * Collects all services tagged with 'health_check.checker' and injects
 * them into the HealthCheckService. Also removes disabled checks based on configuration.
 */
class HealthCheckPass implements CompilerPassInterface
{
    public const TAG_NAME = 'health_check.checker';

    /**
     * Built-in checks that the bundle configuration can switch off, mapped to
     * their config key and their default state when the key is absent.
     *
     * @var array<class-string, array{key: string, default: bool}>
     */
    private const TOGGLEABLE_CHECKS = [
        DatabaseHealthCheck::class => ['key' => 'database', 'default' => true],
        RedisHealthCheck::class => ['key' => 'redis', 'default' => false],
    ];

    public function process(ContainerBuilder $container): void
    {
        // Check if the HealthCheckService exists
        if (!$container->has(HealthCheckService::class)) {
            return;
        }

        $config = $this->getChecksConfig($container);
        $definition = $container->findDefinition(HealthCheckService::class);

        $references = [];

        foreach ($this->getSortedTaggedIds($container) as $id) {
            if (!$this->isEnabled($container, $id, $config)) {
                // Only definitions can be removed; a tagged alias would raise here.
                if ($container->hasDefinition($id)) {
                    $container->removeDefinition($id);
                }

                continue;
            }

            $references[] = new Reference($id);
        }

        // Wrap in an IteratorArgument so checks keep being instantiated lazily,
        // as they are with tagged_iterator. A plain array of references would
        // construct every check — and every connection they hold — up front.
        $definition->setArgument('$healthChecks', new IteratorArgument($references));
    }

    /**
     * Read the bundle's per-check configuration.
     *
     * The parameter is missing whenever the extension has not run — for
     * instance when the compiler pass is exercised on its own — so the absence
     * must not be fatal.
     *
     * @return array<string, mixed>
     */
    private function getChecksConfig(ContainerBuilder $container): array
    {
        if (!$container->hasParameter('health_check.checks')) {
            return [];
        }

        $config = $container->getParameter('health_check.checks');

        return is_array($config) ? $config : [];
    }

    /**
     * Collect tagged service ids, ordered by descending tag priority.
     *
     * @return list<string>
     */
    private function getSortedTaggedIds(ContainerBuilder $container): array
    {
        $entries = [];

        foreach ($container->findTaggedServiceIds(self::TAG_NAME) as $id => $tags) {
            $priority = 0;

            foreach ($tags as $attributes) {
                if (isset($attributes['priority']) && is_numeric($attributes['priority'])) {
                    $priority = (int) $attributes['priority'];

                    break;
                }
            }

            $entries[] = ['id' => $id, 'priority' => $priority];
        }

        // usort is stable as of PHP 8.0, so equal priorities keep registration order.
        usort($entries, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return array_map(static fn (array $entry): string => $entry['id'], $entries);
    }

    /**
     * Determine whether a tagged check should be injected.
     *
     * Resolution is based on the service's actual class rather than a substring
     * of its id: matching on the id alone would silently disable an unrelated
     * application service whose name happens to contain "DatabaseHealthCheck".
     *
     * @param array<string, mixed> $config
     */
    private function isEnabled(ContainerBuilder $container, string $id, array $config): bool
    {
        $class = $container->hasDefinition($id) ? $container->getDefinition($id)->getClass() : null;

        if (null === $class) {
            return true;
        }

        foreach (self::TOGGLEABLE_CHECKS as $builtIn => $toggle) {
            if ($class !== $builtIn && !is_subclass_of($class, $builtIn)) {
                continue;
            }

            $checkConfig = $config[$toggle['key']] ?? null;

            if (is_array($checkConfig) && isset($checkConfig['enabled'])) {
                return (bool) $checkConfig['enabled'];
            }

            return $toggle['default'];
        }

        return true;
    }
}
