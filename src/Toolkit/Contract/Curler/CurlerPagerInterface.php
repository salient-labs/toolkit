<?php declare(strict_types=1);

namespace Salient\Contract\Curler;

use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Salient\Contract\Http\Message\ResponseInterface;
use Salient\Contract\Http\HasHttpHeader;

/**
 * @api
 */
interface CurlerPagerInterface extends HasHttpHeader
{
    /**
     * Get a request to retrieve the first page of data from the endpoint
     *
     * Return `$request` if no special handling is required.
     *
     * Return a {@see CurlerPageRequestInterface} to propagate `$query` changes
     * to {@see CurlerPagerInterface::getPage()} in array form.
     *
     * @param mixed[]|null $query The query applied to `$request`.
     * @return CurlerPageRequestInterface|PsrRequestInterface
     */
    public function getFirstRequest(
        PsrRequestInterface $request,
        CurlerInterface $curler,
        ?array $query = null
    );

    /**
     * Convert data returned by the endpoint to a new page object
     *
     * @template TPage of CurlerPageInterface|null
     *
     * @param mixed $data
     * @param TPage $previousPage
     * @param int|null $previousEntities The number of entities returned from
     * the endpoint via previous pages.
     * @param mixed[]|null $query The query applied to `$request` or returned by
     * {@see CurlerPageRequestInterface::getQuery()}, if applicable.
     * @return (TPage is null ? CurlerPageInterface : TPage)
     */
    public function getPage(
        $data,
        PsrRequestInterface $request,
        ResponseInterface $response,
        CurlerInterface $curler,
        ?CurlerPageInterface $previousPage = null,
        ?int $previousEntities = null,
        ?array $query = null
    ): CurlerPageInterface;
}
