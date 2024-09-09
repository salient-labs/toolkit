<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface;
use OutOfRangeException;

/**
 * @api
 */
interface CurlerPageRequestInterface
{
    /**
     * Check if a page of data can be retrieved from the endpoint
     */
    public function hasNextRequest(): bool;

    /**
     * Get a request to retrieve the next page of data from the endpoint
     *
     * @throws OutOfRangeException if there are no more pages to retrieve.
     */
    public function getNextRequest(): RequestInterface;

    /**
     * Get the query applied to the request to retrieve the next page of data
     *
     * @return mixed[]|null
     * @throws OutOfRangeException if there are no more pages to retrieve.
     */
    public function getNextQuery(): ?array;
}
