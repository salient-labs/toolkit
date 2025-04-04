<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Exception;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Http\Message\ResponseInterface;

/**
 * @api
 */
interface ResponseException extends CurlerException
{
    /**
     * Get the request associated with the exception
     */
    public function getRequest(): PsrRequestInterface;

    /**
     * Get the response associated with the exception
     */
    public function getResponse(): ResponseInterface;
}
