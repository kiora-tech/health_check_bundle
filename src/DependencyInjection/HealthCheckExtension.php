<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Dependency Injection extension for the health check bundle.
 *
 * Loads service definitions and processes bundle configuration.
 */
class HealthCheckExtension extends Extension
{
    /**
     * @param array<string, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store configuration for use in services
        $container->setParameter('health_check.enabled', $config['enabled']);
        $container->setParameter('health_check.checks', $config['checks']);

        // Load service definitions
        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__.'/../../config')
        );
        $loader->load('services.php');
    }

    /**
     * Get the extension alias.
     *
     * This allows the bundle to be configured using 'health_check:' in YAML files.
     */
    public function getAlias(): string
    {
        return 'health_check';
    }
}
