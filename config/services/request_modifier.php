<?php declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use danielburger1337\BffProxyBundle\BffProxyBundle;
use danielburger1337\BffProxyBundle\Service\RequestModifier\FormDataRequestModifier;
use danielburger1337\BffProxyBundle\Service\RequestModifier\FormUrlEncodedRequestModifier;
use danielburger1337\BffProxyBundle\Service\RequestModifier\GenericRequestModifier;
use danielburger1337\BffProxyBundle\Service\RequestModifier\JsonRequestModifier;

return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set(GenericRequestModifier::class)
            ->tag(BffProxyBundle::TAG_REQUEST_MODIFIER, ['priority' => \PHP_INT_MIN])
        ->set(FormDataRequestModifier::class)
            ->tag(BffProxyBundle::TAG_REQUEST_MODIFIER)
        ->set(FormUrlEncodedRequestModifier::class)
            ->tag(BffProxyBundle::TAG_REQUEST_MODIFIER)
        ->set(JsonRequestModifier::class)
            ->tag(BffProxyBundle::TAG_REQUEST_MODIFIER)
    ;
};
