<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle;

use danielburger1337\BffProxyBundle\Controller\BffProxyController;
use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\LocalProxyService;
use danielburger1337\BffProxyBundle\Service\RemoteProxyService;
use danielburger1337\BffProxyBundle\Service\RequestModifier\RequestModifierInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class BffProxyBundle extends AbstractBundle
{
    final public const string TAG_REQUEST_MODIFIER = 'danielburger1337.bff_proxy.request_modifier';

    /** @internal */
    final public const string SERVICE_ID_HTTP_FOUNDATION_FACTORY = 'danielburger1337.bff_proxy.http_foundation_factory';

    /**
     * @codeCoverageIgnore
     */
    #[\Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definitions/*.php');
    }

    /**
     * @template TUpstream of array{
     *          http_client: string,
     *          request_factory: string,
     *          stream_factory: string,
     *          http_foundation_factory: string,
     *          passthrough_request_x_headers: bool,
     *          passthrough_request_headers: string[],
     *          passthrough_response_x_headers: bool,
     *          passthrough_response_headers: string[],
     *          support_file_upload: bool
     *      }
     *
     * @param array{
     *      options_parameter: string,
     *      local_proxy: string|null,
     *      upstreams: array<string, TUpstream>
     * } $config
     */
    #[\Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services/bff_proxy.php');
        $container->import('../config/services/request_modifier.php');

        if (null !== $config['local_proxy']) {
            $container->import('../config/services/local_proxy.php');

            $builder->getDefinition(LocalProxyService::class)
                ->replaceArgument('$name', $config['local_proxy']);
        }

        $builder->getDefinition(RemoteProxyService::class)
            ->replaceArgument('$optionsParameter', $config['options_parameter']);

        $builder->registerForAutoconfiguration(RequestModifierInterface::class)
            ->addTag(self::TAG_REQUEST_MODIFIER);

        $upstreamMap = [];

        foreach ($config['upstreams'] as $name => $upstream) {
            $serviceId = "danielburger1337.bff_proxy.configuration.{$name}";

            $builder->setDefinition($serviceId, new Definition(BffProxyConfiguration::class))
                ->setArgument('$httpClient', new Reference($upstream['http_client']))
                ->setArgument('$requestFactory', new Reference($upstream['request_factory']))
                ->setArgument('$streamFactory', new Reference($upstream['stream_factory']))
                ->setArgument('$httpFoundationFactory', new Reference($upstream['http_foundation_factory']))
                ->setArgument('$passthroughRequestXHeaders', $upstream['passthrough_request_x_headers'])
                ->setArgument('$passthroughRequestHeaders', $upstream['passthrough_request_headers'])
                ->setArgument('$passthroughResponseXHeaders', $upstream['passthrough_response_x_headers'])
                ->setArgument('$passthroughResponseHeaders', $upstream['passthrough_response_headers'])
                ->setArgument('$supportFileUpload', $upstream['support_file_upload'])
            ;

            $upstreamMap[$name] = new Reference($serviceId);
        }

        $builder->getDefinition(BffProxyController::class)
            ->setArgument('$configProvider', ServiceLocatorTagPass::register($builder, $upstreamMap));
    }
}
