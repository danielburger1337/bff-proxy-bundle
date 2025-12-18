<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Request;

class JsonRequestModifier implements RequestModifierInterface
{
    public function supportsRequest(Request $request): bool
    {
        return $request->getContentTypeFormat() === 'json';
    }

    public function createRequest(Request $request, RequestInterface $psrRequest, BffProxyConfiguration $bffConfig): RequestInterface
    {
        return $psrRequest
            ->withHeader('content-type', (string) $request->headers->get('content-type'))
            ->withBody($bffConfig->streamFactory->createStream(\json_encode($request->getPayload()->all(), \JSON_THROW_ON_ERROR)))
        ;
    }
}
