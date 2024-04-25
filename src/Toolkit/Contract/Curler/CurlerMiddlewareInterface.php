<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface;
use Salient\Contract\Http\HttpResponseInterface;
use Closure;

/**
 * @api
 */
interface CurlerMiddlewareInterface
{
    /**
     * Act on a request and generate a response, optionally forwarding the
     * request to the next handler and acting on its response
     *
     * @param Closure(RequestInterface): HttpResponseInterface $next
     */
    public function __invoke(RequestInterface $request, Closure $next, CurlerInterface $curler): HttpResponseInterface;
}
