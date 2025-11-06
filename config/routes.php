<?php

declare(strict_types=1);

use Kiora\HealthCheckBundle\Controller\HealthCheckController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->add('health_check', '/health')
        ->controller([HealthCheckController::class, 'check'])
        ->methods(['GET']);
};
