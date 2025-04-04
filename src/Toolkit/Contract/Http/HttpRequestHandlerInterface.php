<?php declare(strict_types=1);

namespace Salient\Contract\Http;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Closure;

interface HttpRequestHandlerInterface
{
    /**
     * Act on a request and generate a response, optionally forwarding the
     * request to the next handler and acting on its response
     *
     * @param Closure(PsrRequestInterface): PsrResponseInterface $next
     */
    public function __invoke(PsrRequestInterface $request, Closure $next): PsrResponseInterface;
}
