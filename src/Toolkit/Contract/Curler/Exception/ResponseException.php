<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Exception;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\Message\HttpResponseInterface;

/**
 * @api
 */
interface ResponseException extends CurlerException
{
    /**
     * Get the request associated with the exception
     */
    public function getRequest(): RequestInterface;

    /**
     * Get the response associated with the exception
     */
    public function getResponse(): HttpResponseInterface;
}
