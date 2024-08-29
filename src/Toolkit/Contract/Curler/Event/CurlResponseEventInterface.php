<?php declare(strict_types=1);

namespace Salient\Contract\Curler\Event;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;

/**
 * Dispatched after a cURL request is executed
 *
 * @api
 */
interface CurlResponseEventInterface extends CurlEventInterface
{
    /**
     * Get the request sent to the endpoint
     */
    public function getRequest(): RequestInterface;

    /**
     * Get the response received from the endpoint
     */
    public function getResponse(): HttpResponseInterface;
}
