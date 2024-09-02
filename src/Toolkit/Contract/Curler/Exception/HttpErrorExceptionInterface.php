<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;

/**
 * @api
 */
interface HttpErrorExceptionInterface extends CurlerExceptionInterface
{
    /**
     * Get the request associated with the exception
     */
    public function getRequest(): RequestInterface;

    /**
     * Get the response associated with the exception
     */
    public function getResponse(): HttpResponseInterface;

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
