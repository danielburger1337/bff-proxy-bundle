<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Security\Voter;

use danielburger1337\BffProxyBundle\Model\BffProxyVoterSubject;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class BffProxyVoter implements CacheableVoterInterface
{
    final public const string ATTRIBUTE_ALLOW_PROXY = 'danielburger1337.bff_proxy.allow_proxy';

    /**
     * @param BffProxyVoterSubject $subject
     * @param string[]             $attributes
     */
    #[\Override]
    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        // abstain vote by default in case none of the attributes are supported
        $voteResult = self::ACCESS_ABSTAIN;

        foreach ($attributes as $attribute) {
            if ($attribute === self::ATTRIBUTE_ALLOW_PROXY) {
                $voteResult = self::ACCESS_GRANTED;
                break;
            }
        }

        if (null !== $vote) {
            $vote->result = $voteResult;
        }

        return $voteResult;
    }

    #[\Override]
    public function supportsType(string $subjectType): bool
    {
        return $subjectType === BffProxyVoterSubject::class;
    }

    #[\Override]
    public function supportsAttribute(string $attribute): bool
    {
        return $attribute === self::ATTRIBUTE_ALLOW_PROXY;
    }
}
