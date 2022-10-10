<?php

declare(strict_types=1);

namespace Lkrms\Curler\Contract;

use Lkrms\Curler\Curler;

interface ICurlerPager
{
    /**
     * Prepare a Curler instance to request the first page from an endpoint
     *
     */
    public function prepare(Curler $curler): Curler;

    /**
     * Convert data returned by an endpoint to a page object
     *
     */
    public function page(array $data, Curler $curler): ICurlerPage;

}
