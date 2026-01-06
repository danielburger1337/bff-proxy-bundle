<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Model;

use Symfony\Component\HttpFoundation\Request;

final readonly class BffProxyVoterSubject
{
    /**
     * @param string                $upstream         The name of the proxied upstream service.
     * @param string                $route            The route of the upstream service that is being proxied.
     * @param Request               $request          The http-foundation request that will be proxied.
     * @param BffProxyConfiguration $bffConfiguration The configuration of the proxied upstream service.
     */
    public function __construct(
        public string $upstream,
        public string $route,
        public Request $request,
        public BffProxyConfiguration $bffConfiguration,
    ) {
    }
}
