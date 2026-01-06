<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Service;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\RemoteProxyService;
use danielburger1337\BffProxyBundle\Service\RequestModifier\FormUrlEncodedRequestModifier;
use danielburger1337\BffProxyBundle\Service\RequestModifier\JsonRequestModifier;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request as Psr7Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(RemoteProxyService::class)]
class RemoteProxyServiceTest extends TestCase
{
    private const string OPTIONS_PARAMETER = 'bffOptions';

    private RemoteProxyService $service;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new RemoteProxyService(
            self::OPTIONS_PARAMETER,
            [
                new JsonRequestModifier(),
                new FormUrlEncodedRequestModifier(),
            ]
        );
    }

    #[Test]
    public function testJsonRequestModifierIsUsed(): void
    {
        $request = Request::create('/bff-proxy', 'POST', ['test' => 'value'], server: ['CONTENT_TYPE' => 'application/json']);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Psr7Request $input): bool {
                $this->assertEquals('application/json', $input->getHeaderLine('content-type'));
                $this->assertEquals('{"test":"value"}', $input->getBody()->__toString());

                return true;
            }))
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path', $client);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testFormEncodedRequestFactoryIsUsed(): void
    {
        $request = Request::create('/bff-proxy', 'POST', ['test' => 'value'], server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Psr7Request $input): bool {
                $this->assertEquals('application/x-www-form-urlencoded', $input->getHeaderLine('content-type'));
                $this->assertEquals('test=value', $input->getBody()->__toString());

                return true;
            }))
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path', $client);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testProxyRequestAddsQueryParameter(): void
    {
        $queryString = 'param=foo&param2=bar';
        $request = Request::create('/bff-proxy?'.$queryString, 'POST', server: ['QUERY_STRING' => $queryString]);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->withAnyParameters()
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path?param=foo&param2=bar', $client);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testProxyRequestRemovesOptionsParameter(): void
    {
        $queryString = 'param=1&bffOptions[streamed]=false';
        $request = Request::create('/bff-proxy?'.$queryString, 'POST', server: ['QUERY_STRING' => $queryString]);

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->withAnyParameters()
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path?param=1', $client);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testProxyRequestIgnoresRequestXHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');
        $request->headers->set('X-Custom-Header', 'CustomValue');
        $request->headers->set('X-Other-Custom-Header', 'OtherCustomValue');
        $request->headers->set('Accept-Language', 'en_US');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Psr7Request $input): bool {
                $this->assertFalse($input->hasHeader('X-Custom-Header'));
                $this->assertFalse($input->hasHeader('X-Other-Custom-Header'));

                $this->assertTrue($input->hasHeader('Accept-Language'));
                $this->assertEquals('en_US', $input->getHeaderLine('Accept-Language'));

                return true;
            }))
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughRequestXHeaders: false);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testProxyRequestAddsRequestXHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');
        $request->headers->set('X-Custom-Header', 'CustomValue');
        $request->headers->set('X-Other-Custom-Header', 'OtherCustomValue');
        $request->headers->set('ignored-header', 'ignore');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Psr7Request $input): bool {
                $this->assertFalse($input->hasHeader('ignored-header'));

                $this->assertTrue($input->hasHeader('X-Custom-Header'));
                $this->assertEquals('CustomValue', $input->getHeaderLine('X-Custom-Header'));
                $this->assertTrue($input->hasHeader('X-Other-Custom-Header'));
                $this->assertEquals('OtherCustomValue', $input->getHeaderLine('X-Other-Custom-Header'));

                return true;
            }))
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughRequestXHeaders: true);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testProxyRequestIgnoresRequestPassthroughHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');
        $request->headers->set('PassThrough-Header', 'Value');
        $request->headers->set('ignored-header', 'ignore');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Psr7Request $input): bool {
                $this->assertFalse($input->hasHeader('ignored-header'));

                $this->assertTrue($input->hasHeader('PassThrough-Header'));
                $this->assertEquals('Value', $input->getHeaderLine('PassThrough-Header'));

                return true;
            }))
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughRequestXHeaders: true, passthroughRequestHeaders: ['PassThrough-Header']);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testProxyRequestAddsRequestPassthroughHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');
        $request->headers->set('PassThrough-Header', 'Value');
        $request->headers->set('Accept-Language', 'en_US');
        $request->headers->set('ignored-header', 'ignore');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Psr7Request $input): bool {
                $this->assertFalse($input->hasHeader('ignored-header'));

                $this->assertTrue($input->hasHeader('PassThrough-Header'));
                $this->assertEquals('Value', $input->getHeaderLine('PassThrough-Header'));

                $this->assertTrue($input->hasHeader('Accept-Language'));
                $this->assertEquals('en_US', $input->getHeaderLine('Accept-Language'));

                return true;
            }))
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughRequestXHeaders: true, passthroughRequestHeaders: ['PassThrough-Header']);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testProxyRequestAddsXHeaderCombinedWithRequestPassthroughHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');
        $request->headers->set('PassThrough-Header', 'Value');
        $request->headers->set('X-Custom-Header', 'Value');
        $request->headers->set('ignored-header', 'ignore');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function (Psr7Request $input): bool {
                $this->assertFalse($input->hasHeader('ignored-header'));

                $this->assertTrue($input->hasHeader('PassThrough-Header'));
                $this->assertEquals('Value', $input->getHeaderLine('PassThrough-Header'));

                $this->assertTrue($input->hasHeader('X-Custom-Header'));
                $this->assertEquals('Value', $input->getHeaderLine('X-Custom-Header'));

                return true;
            }))
            ->willReturn(new Response());

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughRequestXHeaders: false, passthroughRequestHeaders: ['PassThrough-Header', 'X-Custom-Header']);

        $this->service->proxyRequest('/path', $request, $bffConfig);
    }

    #[Test]
    public function testProxyRequestIgnoresResponseXHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Response(headers: ['X-Custom-Header' => 'Value', 'X-Other-Custom-Header' => 'OtherValue']));

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughResponseXHeaders: false);

        $returnValue = $this->service->proxyRequest('/path', $request, $bffConfig);

        $this->assertFalse($returnValue->headers->has('x-custom-header'));
        $this->assertFalse($returnValue->headers->has('x-other-custom-header'));
    }

    #[Test]
    public function testProxyRequestAddsResponseXHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Response(headers: ['X-Custom-Header' => 'Value', 'X-Other-Custom-Header' => 'OtherValue']));

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughResponseXHeaders: true);

        $returnValue = $this->service->proxyRequest('/path', $request, $bffConfig);

        $this->assertTrue($returnValue->headers->has('x-custom-header'));
        $this->assertEquals('Value', $returnValue->headers->get('x-custom-header'));

        $this->assertTrue($returnValue->headers->has('x-other-custom-header'));
        $this->assertEquals('OtherValue', $returnValue->headers->get('x-other-custom-header'));
    }

    #[Test]
    public function testProxyRequestResponsePassthroughHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Response(headers: ['PassThrough-Header' => 'Value', 'ignored-header' => 'ignore']));

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughResponseHeaders: ['PassThrough-Header']);

        $returnValue = $this->service->proxyRequest('/path', $request, $bffConfig);

        $this->assertTrue($returnValue->headers->has('PassThrough-Header'));
        $this->assertEquals('Value', $returnValue->headers->get('PassThrough-Header'));

        $this->assertFalse($returnValue->headers->has('ignored-header'));
    }

    #[Test]
    public function testProxyRequestAddsXHeaderCombinedWithResponsePassthroughHeaders(): void
    {
        $request = Request::create('/bff-proxy', 'POST');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('sendRequest')
            ->willReturn(new Response(headers: ['PassThrough-Header' => 'Value', 'ignored-header' => 'ignore', 'X-Custom-Header' => 'CustomValue']));

        $bffConfig = $this->createConfiguration($request, '/path', $client, passthroughResponseHeaders: ['PassThrough-Header', 'X-Custom-Header'], passthroughResponseXHeaders: false);

        $returnValue = $this->service->proxyRequest('/path', $request, $bffConfig);

        $this->assertTrue($returnValue->headers->has('PassThrough-Header'));
        $this->assertEquals('Value', $returnValue->headers->get('PassThrough-Header'));

        $this->assertTrue($returnValue->headers->has('X-Custom-Header'));
        $this->assertEquals('CustomValue', $returnValue->headers->get('X-Custom-Header'));
    }

    /**
     * @param string[] $passthroughRequestHeaders
     * @param string[] $passthroughResponseHeaders
     */
    private function createConfiguration(
        Request $request,
        RequestFactoryInterface|string $requestFactory,
        ?ClientInterface $client = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?HttpFoundationFactoryInterface $httpFoundationFactory = null,
        bool $passthroughRequestXHeaders = true,
        array $passthroughRequestHeaders = [],
        bool $passthroughResponseXHeaders = true,
        array $passthroughResponseHeaders = [],
        bool $supportFileUpload = true,
    ): BffProxyConfiguration {
        if (null === $client) {
            $client = $this->createMock(ClientInterface::class);
            $client->expects($this->once())
                ->method('sendRequest')
                ->withAnyParameters()
                ->willReturn(new Response());
        }

        if (\is_string($requestFactory)) {
            $path = $requestFactory;
            $requestFactory = $this->createMock(RequestFactoryInterface::class);
            $requestFactory->expects($this->once())
                ->method('createRequest')
                ->with($request->getMethod(), $path)
                ->willReturn(new Psr7Request($request->getMethod(), $path));
        }

        return new BffProxyConfiguration(
            $client,
            $requestFactory,
            $streamFactory ?? new Psr17Factory(),
            $httpFoundationFactory ?? new HttpFoundationFactory(),
            $passthroughRequestXHeaders,
            $passthroughRequestHeaders,
            $passthroughResponseXHeaders,
            $passthroughResponseHeaders,
            $supportFileUpload
        );
    }
}
