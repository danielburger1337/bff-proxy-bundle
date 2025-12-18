<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Tests\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use danielburger1337\BffProxyBundle\Service\RequestModifier\FormDataRequestModifier;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request as Psr7Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(FormDataRequestModifier::class)]
class FormDataRequestModifierTest extends TestCase
{
    private FormDataRequestModifier $factory;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FormDataRequestModifier();
    }

    #[Test]
    #[DataProvider('dataProviderSupportsRequest')]
    public function testSupportsRequest(string $contentType, bool $expected): void
    {
        $request = Request::create('/', 'POST', ['test' => '1'], [], [], ['CONTENT_TYPE' => $contentType]);

        $this->assertEquals($expected, $this->factory->supportsRequest($request));
    }

    /**
     * @param string[] $expectedNames
     *
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    #[Test]
    #[DataProvider('dataProviderCreateRequest')]
    public function testCreateRequest(array $parameters, bool $supportFileUpload, array $files, array $expectedNames): void
    {
        $request = Request::create('/', 'POST', $parameters, [], $files, ['CONTENT_TYPE' => 'multipart/form-data']);

        $psr17 = new Psr17Factory();
        $bffConfig = new BffProxyConfiguration($this->createStub(ClientInterface::class), $psr17, $psr17, $this->createStub(HttpFoundationFactoryInterface::class), supportFileUpload: $supportFileUpload);

        $psrRequest = $this->factory->createRequest($request, new Psr7Request('POST', '/'), $bffConfig);

        $header = $psrRequest->getHeaderLine('content-type');
        $this->assertStringStartsWith('multipart/form-data; boundary=', $header);

        $returnValue = $psrRequest->getBody()->__toString();

        $count = \substr_count($returnValue, 'Content-Disposition: form-data; name=');
        $this->assertTrue($count === \count($expectedNames));

        foreach ($expectedNames as $name) {
            $this->assertStringContainsString('Content-Disposition: form-data; name="'.$name.'"', $returnValue);
        }
    }

    /**
     * @return list<array{0: array, 1: bool, 2: array, 3: array}>
     *
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    public static function dataProviderCreateRequest(): array
    {
        return [
            [[], false, [], []],
            [[], true, [], []],

            [['key' => 'value'], false, ['file1' => self::createUploadedFile()], ['key']],
            [['key' => 'value', 'scalar' => true], true, ['file1' => self::createUploadedFile()], ['key', 'scalar', 'file1']],

            [[
                'key' => 'value',
                'listKey' => [
                    'val1',
                    'val2',
                    [
                        'key1' => 'val3',
                        'key2' => 'val4',
                    ],
                ],
                'nestedKey' => [
                    'key3' => 'val5',
                    'key4' => 'val6',
                    'key5' => [
                        'val7',
                        'val8',
                        [
                            'key6' => 'val9',
                            'key7' => 'val10',
                        ],
                        [
                            [
                                'val11',
                                'val12',
                            ],
                        ],
                    ],
                ],
            ], true, [], [
                'key',
                'listKey[0]', 'listKey[1]', 'listKey[2][key1]', 'listKey[2][key2]',
                'nestedKey[key3]', 'nestedKey[key4]', 'nestedKey[key5][0]', 'nestedKey[key5][1]', 'nestedKey[key5][2][key6]', 'nestedKey[key5][2][key7]', 'nestedKey[key5][3][0][0]', 'nestedKey[key5][3][0][1]',
            ]],

            [[], true, [
                'file' => self::createUploadedFile(),
                'listFile' => [
                    self::createUploadedFile(),
                    self::createUploadedFile(),
                    [
                        'key1' => self::createUploadedFile(),
                        'key2' => self::createUploadedFile(),
                    ],
                ],
                'nestedFile' => [
                    'key3' => self::createUploadedFile(),
                    'key4' => self::createUploadedFile(),
                    'key5' => [
                        self::createUploadedFile(),
                        self::createUploadedFile(),
                        [
                            'key6' => self::createUploadedFile(),
                            'key7' => self::createUploadedFile(),
                        ],
                        [
                            [
                                self::createUploadedFile(),
                                self::createUploadedFile(),
                            ],
                        ],
                    ],
                ],
            ], [
                'file',
                'listFile[0]', 'listFile[1]', 'listFile[2][key1]', 'listFile[2][key2]',
                'nestedFile[key3]', 'nestedFile[key4]', 'nestedFile[key5][0]', 'nestedFile[key5][1]', 'nestedFile[key5][2][key6]', 'nestedFile[key5][2][key7]', 'nestedFile[key5][3][0][0]', 'nestedFile[key5][3][0][1]',
            ]],
        ];
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function dataProviderSupportsRequest(): array
    {
        return [
            ['multipart/form-data', true],
            ['application/x-www-form-urlencoded', false],
            ['application/json', false],
            ['form', false],
        ];
    }

    private static function createUploadedFile(): UploadedFile
    {
        return new UploadedFile(__DIR__.'/../../fixtures/test.txt', 'test.txt', 'text/plain');
    }
}
