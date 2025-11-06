<?php

declare(strict_types=1);

namespace Kiora\HealthCheckBundle;

use Kiora\HealthCheckBundle\DependencyInjection\Compiler\HealthCheckPass;
use Kiora\HealthCheckBundle\DependencyInjection\HealthCheckExtension;
use Kiora\HealthCheckBundle\HealthCheck\HealthCheckInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Health Check Bundle.
 *
 * Provides comprehensive health check functionality for Symfony applications.
 */
class HealthCheckBundle extends AbstractBundle
{
    /**
     * Register compiler passes and autoconfiguration.
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Register autoconfiguration for HealthCheckInterface
        // This enables the #[AutoconfigureTag] attribute on the interface to work
        $container->registerForAutoconfiguration(HealthCheckInterface::class)
            ->addTag('health_check.checker');

        $container->addCompilerPass(new HealthCheckPass());
    }

    /**
     * Override the default extension to use a custom alias.
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new HealthCheckExtension();
        }

        return $this->extension;
    }
}
