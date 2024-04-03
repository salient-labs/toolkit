<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

class CurlErrorException extends AbstractCurlerException implements RequestExceptionInterface
{
    protected int $CurlError;

    public function __construct(int $curlError, RequestInterface $request)
    {
        $this->CurlError = $curlError;

        parent::__construct(
            sprintf('cURL error %d: %s', $curlError, curl_strerror($curlError)),
            $request,
        );
    }

    public function getCurlError(): int
    {
        return $this->CurlError;
    }

    public function getRequest(): RequestInterface
    {
        return $this->Request;
    }
}
