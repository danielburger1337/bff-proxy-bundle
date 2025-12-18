<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

class LocalProxyService
{
    public function __construct(
        private readonly string $name,
        private readonly UrlMatcherInterface $urlMatcher,
        private readonly HttpKernelInterface $httpKernel,
        private readonly ?LoggerInterface $logger,
    ) {
    }

    public function isUpstreamSupported(string $upstream): bool
    {
        return $this->name === $upstream;
    }

    public function proxyRequest(string $path, Request $request): Response
    {
        $path = \trim($path);
        if ('' === $path) {
            throw new BadRequestHttpException('Missing mandatory parameter "path".');
        }

        if (!\str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        try {
            // reuses the current request context
            $routeAttributes = $this->urlMatcher->match($path);
        } catch (ResourceNotFoundException $e) {
            throw new NotFoundHttpException(previous: $e);
        } catch (MethodNotAllowedException $e) {
            throw new MethodNotAllowedHttpException($e->getAllowedMethods(), $e->getMessage(), $e);
        }

        $this->logger?->info('[BffProxy] Forward to route "{route}".', [
            'route' => $routeAttributes['_route'] ?? 'n/a',
            'route_parameters' => $routeAttributes,
            'request_uri' => $request->getUri(),
            'method' => $request->getMethod(),
        ]);

        // RouterListener won't be invoked here
        $routeAttributes['_route_params'] = $routeAttributes;
        $routeAttributes['_stateless'] = false;

        $subRequest = $request->duplicate(attributes: $routeAttributes);

        return $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST, false);
    }
}
