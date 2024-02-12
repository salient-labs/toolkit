<?php declare(strict_types=1);

namespace Lkrms\Curler\Exception;

use Lkrms\Curler\Curler;

/**
 * Thrown when a libcurl error occurs
 */
class CurlerCurlErrorException extends CurlerException
{
    protected int $CurlError;

    public function __construct(int $curlError, Curler $curler)
    {
        $this->CurlError = $curlError;

        parent::__construct(
            sprintf('cURL error %d: %s', $curlError, curl_strerror($curlError)),
            $curler,
        );
    }

    public function getCurlError(): int
    {
        return $this->CurlError;
    }
}
