<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Exception;

/**
 * @api
 */
interface HttpErrorExceptionInterface extends ResponseExceptionInterface
{
    /**
     * Get the HTTP status code associated with the exception
     */
    public function getStatusCode(): int;

    /**
     * Check if the HTTP status code associated with the exception is 404 (Not
     * Found) or 410 (Gone)
     */
    public function isNotFoundError(): bool;
}
