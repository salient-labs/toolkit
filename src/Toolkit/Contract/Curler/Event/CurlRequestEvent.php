<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Event;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;

/**
 * Dispatched before a cURL request is executed
 *
 * @api
 */
interface CurlRequestEvent extends CurlEvent
{
    /**
     * Get the request being sent to the endpoint
     */
    public function getRequest(): PsrRequestInterface;
}
