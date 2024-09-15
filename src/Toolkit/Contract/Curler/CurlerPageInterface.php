<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface;
use OutOfRangeException;

/**
 * @api
 */
interface CurlerPageInterface
{
    /**
     * Get a list of entities returned by the endpoint
     *
     * @return list<mixed>
     */
    public function getEntities(): array;

    /**
     * Check if more data can be requested from the endpoint
     */
    public function hasNextRequest(): bool;

    /**
     * Get a request to retrieve the next page of data from the endpoint
     *
     * Return a {@see CurlerPageRequestInterface} to propagate query changes to
     * the next {@see CurlerPagerInterface::getPage()} call in array form.
     *
     * @return CurlerPageRequestInterface|RequestInterface
     * @throws OutOfRangeException if no more data can be requested.
     */
    public function getNextRequest();
}
