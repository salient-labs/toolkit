<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface;

/**
 * @api
 */
interface CurlerPageRequestInterface
{
    /**
     * Get a request to retrieve a page of data from the endpoint
     */
    public function getRequest(): RequestInterface;

    /**
     * Get the query applied to the request to retrieve a page of data
     *
     * @return mixed[]|null
     */
    public function getQuery(): ?array;
}
