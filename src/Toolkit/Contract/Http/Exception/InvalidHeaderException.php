<?php declare(strict_types=1);

namespace Salient\Contract\Http\Exception;

/**
 * @api
 */
interface InvalidHeaderException extends HttpException
{
    /**
     * Get the status code to return if a response is derived from the exception
     */
    public function getStatusCode(): ?int;
}
