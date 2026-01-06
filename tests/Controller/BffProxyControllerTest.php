<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Controller;

use danielburger1337\BffProxyBundle\Controller\BffProxyController;
use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\LocalProxyService;
use danielburger1337\BffProxyBundle\Service\RemoteProxyService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Service\ServiceProviderInterface;

class BffProxyControllerTest extends TestCase
{
    #[Test]
    public function testLocalProxyIsUsed(): void
    {
        $request = Request::createFromGlobals();

        $localProxy = $this->createMock(LocalProxyService::class);
        $localProxy->expects($this->once())
            ->method('isUpstreamSupported')
            ->with('local')
            ->willReturn(true);

        $localProxy->expects($this->once())
            ->method('proxyRequest')
            ->with('/path');

        $controller = new BffProxyController(
            $this->createStub(RemoteProxyService::class),
            $this->createStub(ServiceProviderInterface::class),
            $localProxy,
        );

        $controller('local', '/path', $request);
    }

    #[Test]
    public function testLocalProxyIsNotUsedWhenUnsupported(): void
    {
        $request = Request::createFromGlobals();

        $localProxy = $this->createMock(LocalProxyService::class);
        $localProxy->expects($this->once())
            ->method('isUpstreamSupported')
            ->with('local')
            ->willReturn(false);

        $localProxy->expects($this->never())
            ->method('proxyRequest');

        $controller = new BffProxyController(
            $this->createStub(RemoteProxyService::class),
            $this->createServiceProvider(),
            $localProxy,
        );

        $this->expectException(NotFoundHttpException::class);

        $controller('local', '/path', $request);
    }

    #[Test]
    public function testLocalProxyIsUsedWhenRemoteHasEqualName(): void
    {
        $request = Request::createFromGlobals();

        $localProxy = $this->createMock(LocalProxyService::class);
        $localProxy->expects($this->once())
            ->method('isUpstreamSupported')
            ->with('local')
            ->willReturn(true);

        $localProxy->expects($this->once())
            ->method('proxyRequest');

        $remoteProxy = $this->createMock(RemoteProxyService::class);
        $remoteProxy->expects($this->never())
            ->method('proxyRequest');

        $controller = new BffProxyController(
            $remoteProxy,
            $this->createServiceProvider([
                'local' => $this->createStub(BffProxyConfiguration::class),
            ]),
            $localProxy,
        );

        $controller('local', '/path', $request);
    }

    #[Test]
    public function testExceptionIsThrownWhenUpstreamIsUnsupported(): void
    {
        $request = Request::createFromGlobals();

        $controller = new BffProxyController(
            $this->createStub(RemoteProxyService::class),
            $this->createServiceProvider([
                'upstream1' => $this->createStub(BffProxyConfiguration::class),
            ]),
        );

        $this->expectException(NotFoundHttpException::class);

        $controller('other-upstream', '/path', $request);
    }

    #[Test]
    public function testUpstreamIsUsed(): void
    {
        $request = Request::createFromGlobals();

        $config = $this->createStub(BffProxyConfiguration::class);

        $remoteProxy = $this->createMock(RemoteProxyService::class);
        $remoteProxy->expects($this->once())
            ->method('proxyRequest')
            ->with('/path', $request, $config)
            ->willReturn(new Response());

        $controller = new BffProxyController(
            $remoteProxy,
            $this->createServiceProvider([
                'upstream' => $config,
                'other-upstream' => $this->createStub(BffProxyConfiguration::class),
            ]),
        );

        $controller('upstream', '/path', $request);
    }

    #[Test]
    public function testPrependsSlashToRouteWithExistingSlash(): void
    {
        $request = Request::createFromGlobals();
        $stub = $this->createStub(BffProxyConfiguration::class);

        $remoteProxy = $this->createMock(RemoteProxyService::class);
        $remoteProxy->expects($this->once())
            ->method('proxyRequest')
            ->with('/with-slash', $request, $stub)
            ->willReturn(new Response());

        $controller = new BffProxyController(
            $remoteProxy,
            $this->createServiceProvider([
                'upstream' => $stub,
            ]),
        );

        $controller('upstream', '/with-slash', $request);
    }

    #[Test]
    public function testPrependsSlashToRoute(): void
    {
        $request = Request::createFromGlobals();
        $stub = $this->createStub(BffProxyConfiguration::class);

        $remoteProxy = $this->createMock(RemoteProxyService::class);
        $remoteProxy->expects($this->once())
            ->method('proxyRequest')
            ->with('/no-slash', $request, $stub)
            ->willReturn(new Response());

        $controller = new BffProxyController(
            $remoteProxy,
            $this->createServiceProvider([
                'upstream' => $stub,
            ]),
        );

        $controller('upstream', 'no-slash', $request);
    }

    #[Test]
    public function testAuthorizationCheckerIsGrantedDoesNothingOnTrue(): void
    {
        $request = Request::createFromGlobals();

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->withAnyParameters()
            ->willReturn(true);

        $remoteProxy = $this->createMock(RemoteProxyService::class);
        $remoteProxy->expects($this->once())
            ->method('proxyRequest')
            ->withAnyParameters()
            ->willReturn(new Response());

        $controller = new BffProxyController(
            $remoteProxy,
            $this->createServiceProvider([
                'upstream' => $this->createStub(BffProxyConfiguration::class),
            ]),
            authorizationChecker: $authorizationChecker
        );

        $response = $controller('upstream', '/path', $request);

        $this->assertEquals('upstream', $response->headers->get('x-bff-proxy-upstream'));
    }

    #[Test]
    public function testExceptionIsThrownWhenUnauthorized(): void
    {
        $request = Request::createFromGlobals();

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->withAnyParameters()
            ->willReturn(false);

        $controller = new BffProxyController(
            $this->createStub(RemoteProxyService::class),
            $this->createServiceProvider([
                'upstream' => $this->createStub(BffProxyConfiguration::class),
            ]),
            authorizationChecker: $authorizationChecker
        );

        $this->expectException(AccessDeniedException::class);

        $controller('upstream', '/path', $request);
    }

    /**
     * @param array<string, BffProxyConfiguration> $configs
     *
     * @return ServiceProviderInterface<BffProxyConfiguration>
     */
    private function createServiceProvider(array $configs = []): ServiceProviderInterface
    {
        return new class($configs) implements ServiceProviderInterface {
            /**
             * @param array<string, BffProxyConfiguration> $configs
             */
            public function __construct(
                private array $configs,
            ) {
            }

            public function get(string $id): mixed
            {
                return $this->configs[$id] ?? throw new ServiceNotFoundException($id);
            }

            public function has(string $id): bool
            {
                return \array_key_exists($id, $this->configs);
            }

            public function getProvidedServices(): array
            {
                throw new \BadMethodCallException();
            }
        };
    }
}
