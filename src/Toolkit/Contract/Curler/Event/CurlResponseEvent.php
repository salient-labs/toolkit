<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Event;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Http\Message\ResponseInterface;

/**
 * Dispatched after a cURL request is executed
 *
 * @api
 */
interface CurlResponseEvent extends CurlEvent
{
    /**
     * Get the request sent to the endpoint
     */
    public function getRequest(): PsrRequestInterface;

    /**
     * Get the response received from the endpoint
     */
    public function getResponse(): ResponseInterface;
}
