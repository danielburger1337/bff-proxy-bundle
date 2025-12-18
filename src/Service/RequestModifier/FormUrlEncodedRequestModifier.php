<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Request;

class FormUrlEncodedRequestModifier implements RequestModifierInterface
{
    public function supportsRequest(Request $request): bool
    {
        return $request->headers->get('content-type') === 'application/x-www-form-urlencoded';
    }

    public function createRequest(Request $request, RequestInterface $psrRequest, BffProxyConfiguration $bffConfig): RequestInterface
    {
        return $psrRequest
            ->withHeader('content-type', 'application/x-www-form-urlencoded')
            ->withBody($bffConfig->streamFactory->createStream(\http_build_query($request->request->all(), encoding_type: \PHP_QUERY_RFC3986)))
        ;
    }
}
