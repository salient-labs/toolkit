<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Salient\Contract\Http\HttpRequestInterface;
use Salient\Core\Exception\LogicException;

interface CurlerPageInterface
{
    /**
     * Get a list of entities returned by the endpoint
     *
     * @return list<mixed>
     */
    public function getEntities(): array;

    /**
     * Check if there is more data to retrieve from the endpoint
     */
    public function isLastPage(): bool;

    /**
     * Get a request to retrieve the next page of data from the endpoint
     *
     * @throws LogicException if there are no more pages to retrieve.
     */
    public function getNextRequest(HttpRequestInterface $request): HttpRequestInterface;
}
