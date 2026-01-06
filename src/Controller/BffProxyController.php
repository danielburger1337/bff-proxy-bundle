<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Controller;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\LocalProxyService;
use danielburger1337\BffProxyBundle\Service\RemoteProxyService;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Service\ServiceProviderInterface;

class BffProxyController
{
    /**
     * @param ServiceProviderInterface<BffProxyConfiguration> $configProvider
     */
    public function __construct(
        private readonly RemoteProxyService $remoteProxyService,
        private readonly ServiceProviderInterface $configProvider,
        private readonly ?LocalProxyService $localProxyService = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(string $upstream, string $route, Request $request): Response
    {
        if ($this->localProxyService?->isUpstreamSupported($upstream) === true) {
            return $this->localProxyService->proxyRequest($route, $request);
        }

        try {
            $bffConfig = $this->configProvider->get($upstream);
        } catch (NotFoundExceptionInterface $e) {
            throw new NotFoundHttpException(\sprintf('The upstream "%s" has no configuration defined.', $upstream), $e);
        }

        if (!\str_starts_with($route, '/')) {
            $route = '/'.$route;
        }

        $this->logger?->info('[BffProxy] Forwarding "{method}" request to upstream "{upstream}".', [
            'upstream' => $upstream,
            'route' => $route,
            'request_uri' => $request->getUri(),
            'method' => $request->getMethod(),
        ]);

        $response = $this->remoteProxyService->proxyRequest($route, $request, $bffConfig);

        $response->headers->set('x-bff-proxy-upstream', $upstream);

        return $response;
    }
}
