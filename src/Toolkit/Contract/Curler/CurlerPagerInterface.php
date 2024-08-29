<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface;

/**
 * @api
 */
interface CurlerPagerInterface
{
    /**
     * Get a request to retrieve the first page of data from the endpoint
     *
     * Return `$request` if no special handling is required.
     *
     * @param mixed[]|null $query
     */
    public function getFirstRequest(
        RequestInterface $request,
        CurlerInterface $curler,
        ?array $query = null
    ): RequestInterface;

    /**
     * Convert data returned by the endpoint to a new page object
     *
     * @param mixed $data
     */
    public function getPage(
        $data,
        RequestInterface $request,
        CurlerInterface $curler,
        ?CurlerPageInterface $previousPage = null
    ): CurlerPageInterface;
}
