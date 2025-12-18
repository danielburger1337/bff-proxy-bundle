<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use danielburger1337\BffProxyBundle\BffProxyBundle;
use danielburger1337\BffProxyBundle\Controller\BffProxyController;
use danielburger1337\BffProxyBundle\Security\Voter\BffProxyVoter;
use danielburger1337\BffProxyBundle\Service\LocalProxyService;
use danielburger1337\BffProxyBundle\Service\RemoteProxyService;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(BffProxyController::class)
            ->args([
                '$configProvider' => abstract_arg(''),
                '$remoteProxyService' => service(RemoteProxyService::class),
                '$localProxyService' => service(LocalProxyService::class)->nullOnInvalid(),
                '$authorizationChecker' => service(AuthorizationCheckerInterface::class)->nullOnInvalid(),
                '$logger' => service('logger')->nullOnInvalid(),
            ])
            ->tag('monolog.logger', ['channel' => 'router'])
            ->tag('controller.service_arguments')

        ->set(RemoteProxyService::class)
            ->args([
                '$optionsParameter' => abstract_arg(''),
                '$requestModifiers' => tagged_iterator(BffProxyBundle::TAG_REQUEST_MODIFIER),
            ])

        ->set(BffProxyBundle::SERVICE_ID_HTTP_FOUNDATION_FACTORY, HttpFoundationFactory::class)

        ->set(BffProxyVoter::class)
            ->tag('security.voter', ['priority' => -100])
    ;
};
