<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Salient\Contract\Http\HttpRequestInterface;
use Salient\Contract\Http\HttpResponseInterface;

interface CurlerPagerInterface
{
    /**
     * Get a request to retrieve the first page of data from the endpoint
     *
     * Return `$request` if no special handling is required.
     */
    public function getFirstRequest(HttpRequestInterface $request): HttpRequestInterface;

    /**
     * Convert data returned by the endpoint to a new page object
     *
     * @param mixed $data
     */
    public function getPage($data, HttpResponseInterface $response, ?CurlerPageInterface $previousPage = null): CurlerPageInterface;
}
