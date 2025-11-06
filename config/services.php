<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Kiora\HealthCheckBundle\Controller\HealthCheckController;
use Kiora\HealthCheckBundle\HealthCheck\Checks\DatabaseHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\Checks\HttpHealthCheck;
use Kiora\HealthCheckBundle\HealthCheck\Checks\RedisHealthCheck;
use Kiora\HealthCheckBundle\Service\HealthCheckService;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    // Health Check Service
    $services->set(HealthCheckService::class)
        ->arg('$healthChecks', tagged_iterator('health_check.checker'))
        ->public();

    // Controller
    $services->set(HealthCheckController::class)
        ->tag('controller.service_arguments');

    // Built-in Health Checks
    // DatabaseHealthCheck is automatically registered and tagged via interface
    $services->set(DatabaseHealthCheck::class);

    // Note: All classes implementing HealthCheckInterface are automatically
    // tagged with 'health_check.checker' thanks to the #[AutoconfigureTag]
    // attribute on the interface.
    //
    // RedisHealthCheck, S3HealthCheck, and HttpHealthCheck are not auto-registered
    // as they require optional dependencies or manual configuration.
    // Users should register them manually in their application if needed.
};
