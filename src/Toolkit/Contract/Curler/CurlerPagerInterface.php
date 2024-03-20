<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface CurlerPagerInterface
{
    /**
     * Get a request to retrieve the first page of data from the endpoint
     *
     * Return `$request` if no special handling is required.
     */
    public function getFirstRequest(RequestInterface $request): RequestInterface;

    /**
     * Convert data returned by the endpoint to a new page object
     *
     * @param mixed $data
     */
    public function getPage($data, ResponseInterface $response, ?CurlerPageInterface $previousPage = null): CurlerPageInterface;
}
