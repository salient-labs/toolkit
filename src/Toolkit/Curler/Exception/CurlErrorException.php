<?php declare(strict_types=1);

namespace Salient\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Curler\Exception\CurlErrorExceptionInterface;

/**
 * @internal
 */
class CurlErrorException extends AbstractRequestException implements CurlErrorExceptionInterface
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
            $data,
        );
    }

    /**
     * @inheritDoc
     */
    public function getCurlError(): int
    {
        return $this->CurlError;
    }

    /**
     * @inheritDoc
     */
    public function isNetworkError(): bool
    {
        return $this->CurlError === \CURLE_COULDNT_RESOLVE_HOST
            || $this->CurlError === \CURLE_COULDNT_CONNECT
            || $this->CurlError === \CURLE_OPERATION_TIMEOUTED
            || $this->CurlError === \CURLE_SSL_CONNECT_ERROR;
    }
}
