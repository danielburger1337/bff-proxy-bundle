<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Service;

use danielburger1337\BffProxyBundle\Service\LocalProxyService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

#[CoversClass(LocalProxyService::class)]
class LocalProxyServiceTest extends TestCase
{
    #[Test]
    public function testSubRequest(): void
    {
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $httpKernel->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(Request::class), HttpKernelInterface::SUB_REQUEST, false)
            ->willReturn(new Response());

        $service = new LocalProxyService(
            'local',
            $this->createStub(UrlMatcherInterface::class),
            $httpKernel,
            new NullLogger()
        );

        $service->proxyRequest('/path', Request::createFromGlobals());
    }

    #[Test]
    public function testRouteAttributesAreSet(): void
    {
        $routeAttributes = ['a' => 1, 'b' => 2];

        $urlMatcher = $this->createMock(UrlMatcherInterface::class);
        $urlMatcher->expects($this->once())
            ->method('match')
            ->with('/path')
            ->willReturn($routeAttributes);

        $service = new LocalProxyService(
            'local',
            $urlMatcher,
            $this->createStub(HttpKernelInterface::class),
            new NullLogger()
        );

        $request = $this->createMock(Request::class);
        $request->expects($this->once())
            ->method('duplicate')
            ->with(null, null, ['_route_params' => $routeAttributes, ...$routeAttributes, '_stateless' => false, 'is_bff_proxy_request' => true], null, null, null)
            ->willReturnSelf();

        $service->proxyRequest('/path', $request);
    }

    #[Test]
    public function testResourceNotFoundExceptionIsConverted(): void
    {
        $urlMatcher = $this->createMock(UrlMatcherInterface::class);
        $urlMatcher->expects($this->once())
            ->method('match')
            ->with('/non-existant-path')
            ->willThrowException(new ResourceNotFoundException());

        $service = new LocalProxyService(
            'local',
            $urlMatcher,
            $this->createStub(HttpKernelInterface::class),
            new NullLogger()
        );

        $this->expectException(NotFoundHttpException::class);

        $service->proxyRequest('/non-existant-path', Request::createFromGlobals());
    }

    #[Test]
    public function testMethodNotAllowedExceptionIsConverted(): void
    {
        $urlMatcher = $this->createMock(UrlMatcherInterface::class);
        $urlMatcher->expects($this->once())
            ->method('match')
            ->with('/path')
            ->willThrowException(new MethodNotAllowedException(['POST']));

        $service = new LocalProxyService(
            'local',
            $urlMatcher,
            $this->createStub(HttpKernelInterface::class),
            new NullLogger()
        );

        $this->expectException(MethodNotAllowedHttpException::class);

        $service->proxyRequest('/path', Request::createFromGlobals());
    }

    #[Test]
    #[DataProvider('dataProviderIsUpstreamSupported')]
    public function testSupports(string $name, string $upstream, bool $expected): void
    {
        $service = new LocalProxyService(
            $name,
            $this->createStub(UrlMatcherInterface::class),
            $this->createStub(HttpKernelInterface::class),
            new NullLogger()
        );

        $this->assertEquals($expected, $service->isUpstreamSupported($upstream));
    }

    #[Test]
    #[DataProvider('dataProviderEmptyPaths')]
    public function testEmptyPathThrowsException(string $path): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing mandatory parameter "path".');

        $service = new LocalProxyService(
            'local',
            $this->createStub(UrlMatcherInterface::class),
            $this->createStub(HttpKernelInterface::class),
            new NullLogger()
        );
        $service->proxyRequest($path, Request::createFromGlobals());
    }

    #[Test]
    #[DataProvider('dataProviderPrependSlash')]
    public function testSlashPrepend(string $path, string $expectedPath): void
    {
        $urlMatcher = $this->createMock(UrlMatcherInterface::class);
        $urlMatcher->expects($this->once())
            ->method('match')
            ->with($expectedPath)
            ->willReturn([]);

        $service = new LocalProxyService(
            'local',
            $urlMatcher,
            $this->createStub(HttpKernelInterface::class),
            new NullLogger()
        );

        $service->proxyRequest($path, Request::createFromGlobals());
    }

    /**
     * @return list<string[]>
     */
    public static function dataProviderPrependSlash(): array
    {
        return [
            ['path', '/path'],
            ['/path', '/path'],
            ['path/subpath', '/path/subpath'],
            ['/path/subpath', '/path/subpath'],
        ];
    }

    /**
     * @return list<string[]>
     */
    public static function dataProviderEmptyPaths(): array
    {
        return [
            [''],
            [' '],
            ['  '],
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: bool}>
     */
    public static function dataProviderIsUpstreamSupported(): array
    {
        return [
            ['local', 'local', true],
            ['local', 'LOCAL', false],
            ['local', 'lOcAl', false],
            ['local', 'remote', false],
            ['customName', 'customName', true],
            ['customName', 'falseName', false],
            ['remote', 'local', false],
        ];
    }
}
