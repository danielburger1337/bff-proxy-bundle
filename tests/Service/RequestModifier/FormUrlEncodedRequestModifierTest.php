<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\RequestModifier\FormUrlEncodedRequestModifier;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request as Psr7Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(FormUrlEncodedRequestModifier::class)]
class FormUrlEncodedRequestModifierTest extends TestCase
{
    private FormUrlEncodedRequestModifier $factory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FormUrlEncodedRequestModifier();
    }

    #[Test]
    #[DataProvider('dataProviderSupportsRequest')]
    public function testSupportsRequest(string $contentType, bool $expected): void
    {
        $request = Request::create('/', 'POST', ['test' => '1'], [], [], ['CONTENT_TYPE' => $contentType]);

        $this->assertEquals($expected, $this->factory->supportsRequest($request));
    }

    /**
     * @param array<string, string> $parameters
     */
    #[Test]
    #[DataProvider('dataProviderCreateRequest')]
    public function testCreateRequest(string $contentType, array $parameters, string $expected): void
    {
        $request = Request::create('/', 'POST', $parameters, [], [], ['CONTENT_TYPE' => $contentType]);

        $psr17 = new Psr17Factory();
        $bffConfig = new BffProxyConfiguration($this->createStub(ClientInterface::class), $psr17, $psr17, $this->createStub(HttpFoundationFactoryInterface::class));

        $psrRequest = $this->factory->createRequest($request, new Psr7Request('POST', '/'), $bffConfig);

        $this->assertEquals($contentType, $psrRequest->getHeaderLine('content-type'));
        $this->assertEquals($expected, $psrRequest->getBody()->__toString());
    }

    /**
     * @return list<array{0: string, 1: array<array-key, mixed>, 2: string}>
     */
    public static function dataProviderCreateRequest(): array
    {
        return [
            ['application/x-www-form-urlencoded', ['key' => 'value'], 'key=value'],
            ['application/x-www-form-urlencoded', ['key' => 'value', 'otherKey' => 'otherValue'], 'key=value&otherKey=otherValue'],
        ];
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function dataProviderSupportsRequest(): array
    {
        return [
            ['application/x-www-form-urlencoded', true],
            ['multipart/form-data', false],
            ['application/json', false],
            ['form', false],
        ];
    }
}
