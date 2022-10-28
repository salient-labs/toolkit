<?php

declare(strict_types=1);

namespace Lkrms\Curler\Contract;

use Lkrms\Curler\Curler;

interface ICurlerPager
{
    /**
     * Prepare a query string for the first page request
     *
     * Do not include a leading question mark (`?`) in the query string. This
     * will be added by {@see Curler}.
     *
     * Return `null` if no special query handling is required.
     */
    public function prepareQuery(?array $query): ?string;

    /**
     * Prepare a Curler instance to request the first page from an endpoint
     *
     */
    public function prepareCurler(Curler $curler): void;

    /**
     * Convert data returned by an endpoint to a page object
     *
     */
    public function getPage(array $data, Curler $curler): ICurlerPage;

}
