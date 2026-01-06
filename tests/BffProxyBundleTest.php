<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests;

use danielburger1337\BffProxyBundle\BffProxyBundle;
use danielburger1337\BffProxyBundle\Controller\BffProxyController;
use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\LocalProxyService;
use danielburger1337\BffProxyBundle\Service\RemoteProxyService;
use danielburger1337\BffProxyBundle\Service\RequestModifier\FormDataRequestModifier;
use danielburger1337\BffProxyBundle\Service\RequestModifier\FormUrlEncodedRequestModifier;
use danielburger1337\BffProxyBundle\Service\RequestModifier\GenericRequestModifier;
use danielburger1337\BffProxyBundle\Service\RequestModifier\JsonRequestModifier;
use danielburger1337\BffProxyBundle\Service\RequestModifier\RequestModifierInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[CoversClass(BffProxyBundle::class)]
class BffProxyBundleTest extends TestCase
{
    private ContainerBuilder $container;
    private ContainerConfigurator $configurator;
    private BffProxyBundle $bundle;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new ContainerBuilder();

        $loader = new PhpFileLoader($this->container, new FileLocator(\dirname(__DIR__).'/src'));
        $instanceOf = [];

        $this->configurator = new ContainerConfigurator($this->container, $loader, $instanceOf, '', '');
        $this->bundle = new BffProxyBundle();
    }

    #[Test]
    public function testHttpFoundationFactoryIsRegistered(): void
    {
        $this->loadExtension();

        $this->assertTrue($this->container->hasDefinition(BffProxyBundle::SERVICE_ID_HTTP_FOUNDATION_FACTORY));
        $definition = $this->container->getDefinition(BffProxyBundle::SERVICE_ID_HTTP_FOUNDATION_FACTORY);
        $this->assertTrue(\is_subclass_of((string) $definition->getClass(), HttpFoundationFactoryInterface::class));
    }

    #[Test]
    public function testControllerIsRegistered(): void
    {
        $this->loadExtension();

        $this->assertTrue($this->container->hasDefinition(BffProxyController::class));

        $definition = $this->container->getDefinition(BffProxyController::class);

        $this->assertTrue($definition->hasTag('monolog.logger'));
        $this->assertEquals(['channel' => 'router'], $definition->getTag('monolog.logger')[0]);
    }

    #[Test]
    public function testRemoteProxyIsRegistered(): void
    {
        $this->loadExtension(['options_parameter' => 'bffProxy']);

        $this->assertTrue($this->container->hasDefinition(RemoteProxyService::class));

        $definition = $this->container->getDefinition(RemoteProxyService::class);

        $this->assertEquals('bffProxy', $definition->getArgument('$optionsParameter'));
    }

    #[Test]
    public function testLocalProxyIsRegistered(): void
    {
        $this->loadExtension(['local_proxy' => 'local_upstream']);

        $this->assertTrue($this->container->hasDefinition(LocalProxyService::class));

        $definition = $this->container->getDefinition(LocalProxyService::class);

        $this->assertEquals('local_upstream', $definition->getArgument('$name'));

        $this->assertTrue($definition->hasTag('monolog.logger'));
        $this->assertEquals(['channel' => 'router'], $definition->getTag('monolog.logger')[0]);
    }

    #[Test]
    public function testLocalProxyNotRegistered(): void
    {
        $this->loadExtension(['local_proxy' => null]);

        $this->assertFalse($this->container->hasDefinition(LocalProxyService::class));
    }

    #[Test]
    public function testAutoconfigurationOfTags(): void
    {
        $this->loadExtension();

        $childDefinitions = $this->container->getAutoconfiguredInstanceof();

        $this->assertArrayHasKey(RequestModifierInterface::class, $childDefinitions);
        $requestModifier = $childDefinitions[RequestModifierInterface::class];

        $this->assertTrue($requestModifier->hasTag(BffProxyBundle::TAG_REQUEST_MODIFIER));
    }

    #[Test]
    public function testRequestFactoriesAreConfigured(): void
    {
        $this->loadExtension();

        foreach ([GenericRequestModifier::class, FormDataRequestModifier::class, FormUrlEncodedRequestModifier::class, JsonRequestModifier::class] as $factory) {
            $definition = $this->container->getDefinition($factory);
            $this->assertTrue($definition->hasTag(BffProxyBundle::TAG_REQUEST_MODIFIER));

            if ($factory === GenericRequestModifier::class) {
                $this->assertArrayHasKey('priority', $definition->getTag(BffProxyBundle::TAG_REQUEST_MODIFIER)[0]);
            }
        }
    }

    #[Test]
    public function testUpstreamIsConfigured(): void
    {
        $configs = [
            'upstream' => [
                'http_client' => HttpClientInterface::class,
                'request_factory' => RequestFactoryInterface::class,
                'stream_factory' => StreamFactoryInterface::class,
                'http_foundation_factory' => HttpFoundationFactoryInterface::class,
                'passthrough_request_x_headers' => false,
                'passthrough_request_headers' => ['x-custom-request-header'],
                'passthrough_response_x_headers' => false,
                'passthrough_response_headers' => ['x-custom-response-header'],
                'support_file_upload' => false,
            ],
        ];

        $this->loadExtension(['upstreams' => $configs]);

        foreach ($configs as $key => $config) {
            $serviceId = 'danielburger1337.bff_proxy.configuration.'.$key;
            $this->assertTrue($this->container->hasDefinition($serviceId));

            $definition = $this->container->getDefinition($serviceId);
            $this->assertEquals(BffProxyConfiguration::class, $definition->getClass());

            $reference = $definition->getArgument('$httpClient');
            $this->assertInstanceOf(Reference::class, $reference);
            $this->assertEquals($config['http_client'], (string) $reference);

            $reference = $definition->getArgument('$requestFactory');
            $this->assertInstanceOf(Reference::class, $reference);
            $this->assertEquals($config['request_factory'], (string) $reference);

            $reference = $definition->getArgument('$streamFactory');
            $this->assertInstanceOf(Reference::class, $reference);
            $this->assertEquals($config['stream_factory'], (string) $reference);

            $reference = $definition->getArgument('$httpFoundationFactory');
            $this->assertInstanceOf(Reference::class, $reference);
            $this->assertEquals($config['http_foundation_factory'], (string) $reference);

            $this->assertEquals($config['passthrough_request_x_headers'], $definition->getArgument('$passthroughRequestXHeaders'));
            $this->assertEquals($config['passthrough_request_headers'], $definition->getArgument('$passthroughRequestHeaders'));
            $this->assertEquals($config['passthrough_response_x_headers'], $definition->getArgument('$passthroughResponseXHeaders'));
            $this->assertEquals($config['passthrough_response_headers'], $definition->getArgument('$passthroughResponseHeaders'));
            $this->assertEquals($config['support_file_upload'], $definition->getArgument('$supportFileUpload'));
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function loadExtension(array $config = []): void
    {
        $this->bundle->loadExtension([ // @phpstan-ignore argument.type
            'local_proxy' => 'local',
            'options_parameter' => 'bff_proxy',

            'upstreams' => [],

            ...$config,
        ], $this->configurator, $this->container);
    }
}
