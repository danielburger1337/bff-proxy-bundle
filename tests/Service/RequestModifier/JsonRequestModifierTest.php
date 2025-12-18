<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\RequestModifier\JsonRequestModifier;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request as Psr7Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(JsonRequestModifier::class)]
class JsonRequestModifierTest extends TestCase
{
    private JsonRequestModifier $factory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new JsonRequestModifier();
    }

    #[Test]
    #[DataProvider('dataProviderSupportsRequest')]
    public function testSupportsRequest(string $contentType, bool $expected): void
    {
        $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => $contentType], '{"test": true}');

        $this->assertEquals($expected, $this->factory->supportsRequest($request));
    }

    /**
     * @param array<string, string> $parameters
     */
    #[Test]
    #[DataProvider('dataProviderCreateRequest')]
    public function testCreateRequest(string $contentType, array $parameters, string $contents, string $expected): void
    {
        $request = Request::create('/', 'POST', $parameters, [], [], ['CONTENT_TYPE' => $contentType], $contents);

        $psr17 = new Psr17Factory();
        $bffConfig = new BffProxyConfiguration($this->createStub(ClientInterface::class), $psr17, $psr17, $this->createStub(HttpFoundationFactoryInterface::class));

        $psrRequest = $this->factory->createRequest($request, new Psr7Request('POST', '/'), $bffConfig);

        $this->assertEquals($contentType, $psrRequest->getHeaderLine('content-type'));
        $this->assertEquals($expected, $psrRequest->getBody()->__toString());
    }

    /**
     * @return list<array{0: string, 1: array<array-key, mixed>, 2: string, 3: string}>
     */
    public static function dataProviderCreateRequest(): array
    {
        return [
            // When Symfony decodes body
            ['application/json', [], '{"origin":"content"}', '{"origin":"content"}'],
            ['application/x-json', [], '{"origin":"content"}', '{"origin":"content"}'],

            // When body was already decoded in $_POST (e.g. pecl json_post ext is installed)
            ['application/json', ['origin' => 'params'], '', '{"origin":"params"}'],
            ['application/x-json', ['origin' => 'params'], '', '{"origin":"params"}'],

            // Should not happen
            ['application/json', ['origin' => 'params'], '{"origin":"content"}', '{"origin":"params"}'],
            ['application/x-json', ['origin' => 'params'], '{"origin":"content"}', '{"origin":"params"}'],
        ];
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function dataProviderSupportsRequest(): array
    {
        return [
            ['application/json', true],
            ['application/x-json', true],
            ['json', false],
            ['text/json', false],
        ];
    }
}
