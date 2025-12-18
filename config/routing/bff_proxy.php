<?php declare(strict_types=1);

use danielburger1337\BffProxyBundle\Controller\BffProxyController;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\Requirement\Requirement;

return static function (RoutingConfigurator $routes): void {
    $routes->add('bff_proxy', '/bff-proxy/{upstream}/{route}')
        ->controller(BffProxyController::class)
        ->requirements([
            'upstream' => Requirement::ASCII_SLUG,
            'route' => Requirement::CATCH_ALL,
        ])
    ;
};
