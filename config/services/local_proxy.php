<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use danielburger1337\BffProxyBundle\Service\LocalProxyService;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(LocalProxyService::class)
            ->args([
                '$name' => abstract_arg(''),
                '$urlMatcher' => service(UrlMatcherInterface::class),
                '$httpKernel' => service(HttpKernelInterface::class),
                '$logger' => service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'router'])
    ;
};
