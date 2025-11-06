<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle\DependencyInjection\Compiler;

use Kiora\HealthCheckBundle\Service\HealthCheckService;
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
    public function process(ContainerBuilder $container): void
    {
        // Check if the HealthCheckService exists
        if (!$container->has(HealthCheckService::class)) {
            return;
        }

        // Get configuration
        $config = $container->getParameter('health_check.checks');

        $definition = $container->findDefinition(HealthCheckService::class);

        // Find all services tagged with 'health_check.checker'
        $taggedServices = $container->findTaggedServiceIds('health_check.checker');

        $references = [];
        foreach (array_keys($taggedServices) as $id) {
            // Check if this is a built-in check that can be disabled
            $shouldInclude = true;

            // Database check
            if (str_contains($id, 'DatabaseHealthCheck')) {
                $shouldInclude = $config['database']['enabled'] ?? true;
            }
            // Redis check
            elseif (str_contains($id, 'RedisHealthCheck')) {
                $shouldInclude = $config['redis']['enabled'] ?? false;
            }

            if ($shouldInclude) {
                $references[] = new Reference($id);
            } else {
                // Remove the service definition if disabled
                $container->removeDefinition($id);
            }
        }

        // Inject all enabled health checks into the service
        $definition->setArgument('$healthChecks', $references);
    }
}
