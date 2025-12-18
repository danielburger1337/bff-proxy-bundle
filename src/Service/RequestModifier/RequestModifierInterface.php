<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Request;

interface RequestModifierInterface
{
    public function supportsRequest(Request $request): bool;

    public function createRequest(Request $request, RequestInterface $psrRequest, BffProxyConfiguration $bffConfig): RequestInterface;
}
