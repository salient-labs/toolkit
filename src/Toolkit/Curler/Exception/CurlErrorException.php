<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

class CurlErrorException extends AbstractCurlerException implements NetworkExceptionInterface
{
    protected int $CurlError;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        int $curlError,
        RequestInterface $request,
        array $data = []
    ) {
        $this->CurlError = $curlError;

        parent::__construct(
            sprintf('cURL error %d: %s', $curlError, curl_strerror($curlError)),
            $request,
            null,
            $data,
        );
    }

    /**
     * Get the error code reported by cURL
     */
    public function getCurlError(): int
    {
        return $this->CurlError;
    }

    /**
     * Get the request that triggered the exception
     */
    public function getRequest(): RequestInterface
    {
        return $this->Request;
    }
}
