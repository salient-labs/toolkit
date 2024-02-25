<?php declare(strict_types=1);

namespace Salient\Curler\Contract;

use Salient\Curler\Curler;

/**
 * Implements retrieval and extraction of paginated data
 *
 * Instances may be reused. If necessary, cleanup between requests should be
 * performed in {@see ICurlerPager::prepareQuery()}.
 *
 * Inheritors may be expected to service requests for entities via
 * {@see ICurlerPager::getPage()} in scenarios where paging is unnecessary
 * and/or unsupported. This may require deferral of pagination-related checks
 * until page 2 is requested.
 */
interface ICurlerPager
{
    /**
     * Prepare a query string for the first page request
     *
     * Return `$query` if no special handling is required.
     *
     * @param mixed[]|null $query
     * @return mixed[]|null
     */
    public function prepareQuery(?array $query): ?array;

    /**
     * Prepare a Curler instance to request the first page from an endpoint
     */
    public function prepareCurler(Curler $curler): Curler;

    /**
     * Prepare POST data for the first page request
     *
     * Return `$data` if no special handling is required.
     *
     * @param mixed[]|object|null $data
     * @return mixed[]|object|null
     */
    public function prepareData($data);

    /**
     * Convert data returned by an endpoint to a page object
     *
     * @param mixed $data
     */
    public function getPage($data, Curler $curler, ?ICurlerPage $previous = null): ICurlerPage;
}
