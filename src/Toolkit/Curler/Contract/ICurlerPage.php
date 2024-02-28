<?php declare(strict_types=1);

namespace Salient\Curler\Contract;

use Salient\Contract\Http\HttpHeadersInterface;

interface ICurlerPage
{
    /**
     * Return data extracted from the upstream response
     *
     * @return mixed[]
     */
    public function entities(): array;

    /**
     * Return the number of entities retrieved so far
     */
    public function entityCount(): int;

    /**
     * Return true if no more data is available
     */
    public function isLastPage(): bool;

    /**
     * Return the URL of the next page
     *
     * If the URL has a query string, it should be included.
     */
    public function nextUrl(): string;

    /**
     * Return data to send in the body of the request for the next page
     *
     * @return mixed[]|null
     */
    public function nextData(): ?array;

    /**
     * Return the HTTP headers to use when requesting the next page
     *
     * Return `null` to use the same headers sent with the last request.
     */
    public function nextHeaders(): ?HttpHeadersInterface;
}
