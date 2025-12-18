<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Model;

final readonly class BffProxyVoterSubject
{
    public function __construct(
        public string $upstream,
        public BffProxyConfiguration $bffConfiguration,
    ) {
    }
}
