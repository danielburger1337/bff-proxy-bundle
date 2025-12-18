<?php declare(strict_types=1);

namespace danielburger1337\BffProxyBundle\Service\RequestModifier;

use danielburger1337\BffProxyBundle\Model\BffProxyConfiguration;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Component\Mime\Part\TextPart;

class FormDataRequestModifier implements RequestModifierInterface
{
    public function supportsRequest(Request $request): bool
    {
        return $request->getContentTypeFormat() === 'form' && $request->headers->get('content-type') !== 'application/x-www-form-urlencoded';
    }

    public function createRequest(Request $request, RequestInterface $psrRequest, BffProxyConfiguration $bffConfig): RequestInterface
    {
        if ($bffConfig->supportFileUpload) {
            $formData = new FormDataPart([
                ...$this->getData($request->request->all()),
                ...$this->getFiles($request->files->all()),
            ]);
        } else {
            $formData = new FormDataPart($this->getData($request->request->all()));
        }

        /** @var HeaderInterface $header */
        foreach ($formData->getPreparedHeaders()->all() as $name => $header) {
            $psrRequest = $psrRequest->withAddedHeader(
                $name,
                $header->getBodyAsString()
            );
        }

        return $psrRequest->withBody(
            $bffConfig->streamFactory->createStream($formData->bodyToString())
        );
    }

    /**
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    private function getData(array $parameters): array
    {
        $data = [];
        foreach ($parameters as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->getData($value);
                continue;
            }

            if (\is_scalar($value)) {
                $value = (string) $value;
            }

            $data[$key] = new TextPart($value);
        }

        return $data;
    }

    /**
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    private function getFiles(array $uploadedFiles): array
    {
        $files = [];

        foreach ($uploadedFiles as $key => $value) {
            if ($value instanceof UploadedFile) {
                $path = $value->getRealPath();
                if (false !== $path) {
                    $files[$key] = DataPart::fromPath($path, $value->getClientOriginalName(), $value->getClientMimeType());
                }

                continue;
            }

            $files[$key] = $this->getFiles($value);
        }

        return $files;
    }
}
