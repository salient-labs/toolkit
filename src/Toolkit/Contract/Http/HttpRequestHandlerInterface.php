<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Closure;

interface HttpRequestHandlerInterface
{
    /**
     * Act on a request and generate a response, optionally forwarding the
     * request to the next handler and acting on its response
     *
     * @param Closure(RequestInterface): ResponseInterface $next
     */
    public function __invoke(RequestInterface $request, Closure $next): ResponseInterface;
}
