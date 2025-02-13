<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Exception;

/**
 * @api
 */
interface CurlErrorException extends RequestException
{
    /**
     * Get the cURL error code associated with the exception
     */
    public function getCurlError(): int;

    /**
     * Check if a network error caused the exception
     */
    public function isNetworkError(): bool;
}
