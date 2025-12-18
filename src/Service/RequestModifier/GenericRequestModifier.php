<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Request;

class GenericRequestModifier implements RequestModifierInterface
{
    public function supportsRequest(Request $request): bool
    {
        return true;
    }

    public function createRequest(Request $request, RequestInterface $psrRequest, BffProxyConfiguration $bffConfig): RequestInterface
    {
        $contentType = $request->headers->get('content-type');
        if (null !== $contentType) {
            $psrRequest = $psrRequest->withHeader('content-type', $contentType);
        }

        return $psrRequest->withBody($bffConfig->streamFactory->createStream($request->getContent()));
    }
}
