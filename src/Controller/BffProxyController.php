<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Controller;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Model\BffProxyVoterSubject;
use danielburger1337\BffProxyBundle\Security\Voter\BffProxyVoter;
use danielburger1337\BffProxyBundle\Service\LocalProxyService;
use danielburger1337\BffProxyBundle\Service\RemoteProxyService;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
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
        private readonly ?AuthorizationCheckerInterface $authorizationChecker = null,
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

        if (null !== $this->authorizationChecker) {
            $sub = new BffProxyVoterSubject($upstream, $route, $request, $bffConfig);
            $accessDecision = new AccessDecision();
            if (!$this->authorizationChecker->isGranted(BffProxyVoter::ATTRIBUTE_ALLOW_PROXY, $sub, $accessDecision)) {
                $exception = new AccessDeniedException();
                $exception->setSubject($sub);
                $exception->setAccessDecision($accessDecision);
                $exception->setAttributes(BffProxyVoter::ATTRIBUTE_ALLOW_PROXY);

                throw $exception;
            }
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
