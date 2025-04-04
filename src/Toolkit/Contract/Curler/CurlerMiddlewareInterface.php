<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Http\Message\ResponseInterface;
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
     * @param Closure(PsrRequestInterface): ResponseInterface $next
     */
    public function __invoke(PsrRequestInterface $request, Closure $next, CurlerInterface $curler): ResponseInterface;
}
