<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Event;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Http\Message\HttpResponseInterface;

/**
 * Dispatched when a request is resolved from the response cache
 *
 * @api
 */
interface ResponseCacheHitEvent extends CurlerEvent
{
    /**
     * Get the request resolved from the response cache
     */
    public function getRequest(): PsrRequestInterface;

    /**
     * Get the response originally received from the endpoint
     */
    public function getResponse(): HttpResponseInterface;
}
