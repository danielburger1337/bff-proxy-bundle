<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Model;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;

#[CoversClass(BffProxyConfiguration::class)]
class BffProxyConfigurationTest extends TestCase
{
    /**
     * @param string[] $headers
     * @param string[] $expected
     */
    #[Test]
    #[DataProvider('dataProviderHeaders')]
    public function testPassthroughHeadersToLower(array $headers, array $expected): void
    {
        $bffConfig = new BffProxyConfiguration(
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            $this->createStub(StreamFactoryInterface::class),
            $this->createStub(HttpFoundationFactoryInterface::class),
            passthroughRequestHeaders: $headers,
            passthroughResponseHeaders: $headers
        );

        $this->assertEquals([...BffProxyConfiguration::DEFAULT_REQUEST_PASSTHROUGH_HEADER_LIST, ...$expected], $bffConfig->passthroughRequestHeaders);
        $this->assertEquals([...BffProxyConfiguration::DEFAULT_RESPONSE_PASSTHROUGH_HEADER_LIST, ...$expected], $bffConfig->passthroughResponseHeaders);
    }

    /**
     * @return list<array{0: string[], 1: string[]}>
     */
    public static function dataProviderHeaders(): array
    {
        return [
            [[], []],
            [['X-Custom-Header'], ['x-custom-header']],
            [['X-CUSTOM-HEADER', 'x-other-header'], ['x-custom-header', 'x-other-header']],
            [['x-custom-header'], ['x-custom-header']],
            [['x-custom-header', 'x-Other-HeaDer'], ['x-custom-header', 'x-other-header']],
        ];
    }
}
