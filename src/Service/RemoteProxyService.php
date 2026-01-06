<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Service;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\RequestModifier\RequestModifierInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RemoteProxyService
{
    /**
     * @param iterable<RequestModifierInterface> $requestModifiers
     */
    public function __construct(
        private readonly string $optionsParameter,
        private readonly iterable $requestModifiers,
    ) {
    }

    public function proxyRequest(string $path, Request $request, BffProxyConfiguration $bffConfig): Response
    {
        $queryParams = $request->query->all();
        unset($queryParams[$this->optionsParameter]);

        if (\count($queryParams) > 0) {
            $path .= '?'.\http_build_query($queryParams);
        }

        $psrRequest = $bffConfig->requestFactory->createRequest($request->getMethod(), $path);

        foreach ($request->headers->all() as $headerName => $headerValues) {
            if (($bffConfig->passthroughRequestXHeaders && \str_starts_with($headerName, 'x-')) || \in_array($headerName, $bffConfig->passthroughRequestHeaders, true)) {
                $psrRequest = $psrRequest->withAddedHeader($headerName, $headerValues); // @phpstan-ignore argument.type
            }
        }

        foreach ($this->requestModifiers as $modifier) {
            if ($modifier->supportsRequest($request)) {
                $psrRequest = $modifier->createRequest($request, $psrRequest, $bffConfig);
                break;
            }
        }

        $psrResponse = $bffConfig->httpClient->sendRequest($psrRequest);

        $bffOptions = new InputBag($request->query->all($this->optionsParameter));
        $response = $bffConfig->httpFoundationFactory->createResponse($psrResponse, $bffOptions->getBoolean('streamed', false));

        foreach ($response->headers->keys() as $key) {
            if ($bffConfig->passthroughResponseXHeaders && \str_starts_with($key, 'x-')) {
                continue;
            }

            if (!\in_array($key, $bffConfig->passthroughResponseHeaders, true)) {
                $response->headers->remove($key);
            }
        }

        return $response;
    }
}
