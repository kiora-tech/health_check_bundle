<?php

declare(strict_types=1);

use Kiora\HealthCheckBundle\Controller\HealthCheckController;
use Kiora\HealthCheckBundle\Controller\PingController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Route names match the #[Route] attributes on the controllers so that either
 * loading strategy yields the same names. Importing this file is the supported
 * path: the bundle's controllers are not scanned for attributes by default.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('health_check', '/health')
        ->controller([HealthCheckController::class, 'check'])
        ->methods(['GET']);

    $routes->add('health_readiness', '/ready')
        ->controller([HealthCheckController::class, 'readiness'])
        ->methods(['GET']);

    $routes->add('health_ping', '/ping')
        ->controller([PingController::class, 'ping'])
        ->methods(['GET']);
};
