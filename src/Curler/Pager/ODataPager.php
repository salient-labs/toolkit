<?php

declare(strict_types=1);

namespace Lkrms\Curler\Pager;

use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Curler;
use Lkrms\Curler\Support\CurlerPageBuilder;

class ODataPager implements ICurlerPager
{
    /**
     * @var string|null
     */
    private $Prefix;

    /**
     * @var int|null
     */
    private $MaxPageSize;

    /**
     * @param string|null $prefix The OData property prefix, e.g. `"@odata."`.
     * Extrapolated from the `OData-Version` HTTP header if `null`.
     */
    public function __construct(?int $maxPageSize = null, ?string $prefix = null)
    {
        $this->Prefix      = $prefix;
        $this->MaxPageSize = $maxPageSize;
    }

    public function prepare(Curler $curler): Curler
    {
        if (!is_null($this->MaxPageSize))
        {
            $curler->Headers->addHeader("Prefer", "odata.maxpagesize={$this->MaxPageSize}");
        }

        return $curler;
    }

    public function page(array $data, Curler $curler): ICurlerPage
    {
        $prefix = $this->Prefix ?: (($curler->ResponseHeadersByName["odata-version"] ?? null) == "4.0"
            ? "@odata."
            : "@");

        return CurlerPageBuilder::build()
            ->entities($data["value"])
            ->curler($curler)
            ->nextUrl($data[$prefix . "nextLink"] ?? null)
            ->go();
    }

}
