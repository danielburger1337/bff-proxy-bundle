<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\RequestModifier\GenericRequestModifier;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request as Psr7Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(GenericRequestModifier::class)]
class GenericRequestModifierTest extends TestCase
{
    private GenericRequestModifier $factory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new GenericRequestModifier();
    }

    #[Test]
    #[DataProvider('dataProviderSupportsRequest')]
    public function testSupportsRequest(string $method, ?string $contentType, bool $expected): void
    {
        $headers = [];
        if (null !== $contentType) {
            $headers['CONTENT_TYPE'] = $contentType;
        }

        $request = Request::create('/', $method, [], [], [], $headers);

        $this->assertEquals($expected, $this->factory->supportsRequest($request));
    }

    /**
     * @param string|resource $contents
     */
    #[Test]
    #[DataProvider('dataProviderCreateRequest')]
    public function testCreateRequest(?string $contentType, mixed $contents, string $expected): void
    {
        $headers = [];
        if (null !== $contentType) {
            $headers['CONTENT_TYPE'] = $contentType;
        }

        $request = Request::create('/', 'POST', [], [], [], $headers, $contents);

        $psr17 = new Psr17Factory();
        $bffConfig = new BffProxyConfiguration($this->createStub(ClientInterface::class), $psr17, $psr17, $this->createStub(HttpFoundationFactoryInterface::class));

        $psrRequest = $this->factory->createRequest($request, new Psr7Request('POST', '/'), $bffConfig);

        if (null !== $contentType) {
            $this->assertEquals($contentType, $psrRequest->getHeaderLine('content-type'));
        }
        $this->assertEquals($expected, $psrRequest->getBody()->__toString());
    }

    /**
     * @return list<array{0: string|null, 1: string|resource|false, 2: string}>
     */
    public static function dataProviderCreateRequest(): array
    {
        return [
            [null, '', ''],
            ['application/json', '{"origin":"content"}', '{"origin":"content"}'],
            ['application/x-json', 'hello world', 'hello world'],
            ['application/x-json', '', ''],
            ['application/x-json', \fopen(__DIR__.'/../../fixtures/test.txt', 'r'), 'hello world'],
        ];
    }

    /**
     * @return list<array{0: string, 1:string|null, 2: bool}>
     */
    public static function dataProviderSupportsRequest(): array
    {
        return [
            ['GET', null, true],
            ['POST', null, true],
            ['PATCH', null, true],
            ['POST', 'text/plain', true],
            ['POST', 'application/json', true],
            ['PATCH', 'doesnt-exist', true],
        ];
    }
}
