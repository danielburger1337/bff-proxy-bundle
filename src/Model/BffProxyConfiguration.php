<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Model;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;

/**
 * @internal
 */
class BffProxyConfiguration
{
    /** @var string[] */
    public const array DEFAULT_REQUEST_PASSTHROUGH_HEADER_LIST = [
        'accept', 'accept-language', 'range',
    ];

    /** @var string[] */
    public const array DEFAULT_RESPONSE_PASSTHROUGH_HEADER_LIST = [
        'cache-control', 'expires', 'last-modified', 'pragma',
        'content-language', 'content-length', 'content-type',
        'www-authenticate', 'range',
    ];

    /** @var string[] */
    public private(set) array $passthroughRequestHeaders = self::DEFAULT_REQUEST_PASSTHROUGH_HEADER_LIST;

    /** @var string[] */
    public private(set) array $passthroughResponseHeaders = self::DEFAULT_RESPONSE_PASSTHROUGH_HEADER_LIST;

    /**
     * @param string[] $passthroughRequestHeaders
     * @param string[] $passthroughResponseHeaders
     */
    public function __construct(
        public readonly ClientInterface $httpClient,
        public readonly RequestFactoryInterface $requestFactory,
        public readonly StreamFactoryInterface $streamFactory,
        public readonly HttpFoundationFactoryInterface $httpFoundationFactory,

        public readonly bool $passthroughRequestXHeaders = true,
        array $passthroughRequestHeaders = [],

        public readonly bool $passthroughResponseXHeaders = true,
        array $passthroughResponseHeaders = [],

        public readonly bool $supportFileUpload = true,
    ) {
        foreach ($passthroughRequestHeaders as $header) {
            $this->passthroughRequestHeaders[] = \strtolower($header);
        }
        foreach ($passthroughResponseHeaders as $header) {
            $this->passthroughResponseHeaders[] = \strtolower($header);
        }
    }
}
