<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Security\Voter;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Model\BffProxyVoterSubject;
use danielburger1337\BffProxyBundle\Security\Voter\BffProxyVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[CoversClass(BffProxyVoter::class)]
class BffProxyVoterTest extends TestCase
{
    private BffProxyVoter $voter;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->voter = new BffProxyVoter();
    }

    /**
     * @param string[] $attribute
     */
    #[Test]
    #[DataProvider('dataProviderVoter')]
    public function testVote(array $attribute, bool $includeVote, int $expected): void
    {
        $token = $this->createStub(TokenInterface::class);

        $subject = new BffProxyVoterSubject('upstream', '/route', new BffProxyConfiguration(
            $this->createStub(ClientInterface::class),
            $this->createStub(RequestFactoryInterface::class),
            $this->createStub(StreamFactoryInterface::class),
            $this->createStub(HttpFoundationFactoryInterface::class)
        ));

        $vote = $includeVote ? new Vote() : null;

        $returnValue = $this->voter->vote($token, $subject, $attribute, $vote);
        $this->assertEquals($expected, $returnValue);

        if (null !== $vote) {
            $this->assertEquals($returnValue, $vote->result);
            $this->assertEquals($expected, $vote->result);
        }
    }

    /**
     * @return list<array{0: string[], 1: bool, 2: int}>
     */
    public static function dataProviderVoter(): array
    {
        return [
            [[BffProxyVoter::ATTRIBUTE_ALLOW_PROXY], true, VoterInterface::ACCESS_GRANTED],
            [[BffProxyVoter::ATTRIBUTE_ALLOW_PROXY], false, VoterInterface::ACCESS_GRANTED],

            [[BffProxyVoter::ATTRIBUTE_ALLOW_PROXY, 'unsupportedAttribute'], true, VoterInterface::ACCESS_GRANTED],
            [[BffProxyVoter::ATTRIBUTE_ALLOW_PROXY, 'unsupportedAttribute'], false, VoterInterface::ACCESS_GRANTED],

            [['unsupportedAttribute', BffProxyVoter::ATTRIBUTE_ALLOW_PROXY], true, VoterInterface::ACCESS_GRANTED],
            [['unsupportedAttribute', BffProxyVoter::ATTRIBUTE_ALLOW_PROXY], false, VoterInterface::ACCESS_GRANTED],

            [['unsupportedAttribute', BffProxyVoter::ATTRIBUTE_ALLOW_PROXY, 'otherUnsupportedAttribute'], true, VoterInterface::ACCESS_GRANTED],
            [['unsupportedAttribute', BffProxyVoter::ATTRIBUTE_ALLOW_PROXY, 'otherUnsupportedAttribute'], false, VoterInterface::ACCESS_GRANTED],

            [['unsupportedAttribute'], true, VoterInterface::ACCESS_ABSTAIN],
            [['unsupportedAttribute'], false, VoterInterface::ACCESS_ABSTAIN],

            [['unsupportedAttribute', 'otherUnsupportedAttribute'], true, VoterInterface::ACCESS_ABSTAIN],
            [['unsupportedAttribute', 'otherUnsupportedAttribute'], false, VoterInterface::ACCESS_ABSTAIN],

            [[], true, VoterInterface::ACCESS_ABSTAIN],
            [[], false, VoterInterface::ACCESS_ABSTAIN],
        ];
    }

    #[Test]
    #[DataProvider('dataProviderSupportsType')]
    public function testSupportsType(string $subjectType, bool $expected): void
    {
        $returnValue = $this->voter->supportsType($subjectType);
        $this->assertEquals($expected, $returnValue);
    }

    #[Test]
    #[DataProvider('dataProviderSupportsAttribute')]
    public function testSupportsAttribute(string $attribute, bool $expected): void
    {
        $returnValue = $this->voter->supportsAttribute($attribute);
        $this->assertEquals($expected, $returnValue);
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function dataProviderSupportsType(): array
    {
        return [
            [BffProxyVoterSubject::class, true],
            ['danielburger1337\\BffProxyBundle\\Model\\BffProxyVoterSubject', true],
            ['BffProxyVoterSubject', false],
            ['fooBar', false],
        ];
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function dataProviderSupportsAttribute(): array
    {
        return [
            [BffProxyVoter::ATTRIBUTE_ALLOW_PROXY, true],
            ['danielburger1337.bff_proxy.allow_proxy', true],
            ['foo_bar', false],
        ];
    }
}
