<?php declare(strict_types=1);

namespace Lkrms\Curler\Pager;

use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Contract\ICurlerPager;
use Lkrms\Curler\Support\CurlerPageBuilder;
use Lkrms\Curler\Curler;
use Salient\Core\Utility\Pcre;
use Salient\Http\Catalog\HttpHeader;

final class ODataPager implements ICurlerPager
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
        $this->Prefix = $prefix;
        $this->MaxPageSize = $maxPageSize;
    }

    public function prepareQuery(?array $query): ?array
    {
        return $query;
    }

    public function prepareData($data)
    {
        return $data;
    }

    public function prepareCurler(Curler $curler): Curler
    {
        if ($this->MaxPageSize === null) {
            return $curler;
        }
        /** @todo wrangle `Prefer` headers in `HttpHeadersInterface`? */
        $preference = sprintf('odata.maxpagesize=%d', $this->MaxPageSize);
        $value = $curler->Headers->getHeader(HttpHeader::PREFER);
        $pattern = '/^odata\.maxpagesize\h*=/i';
        $replace = Pcre::grep($pattern, $value);
        if (count($replace) === 1) {
            reset($replace);
            $value[key($replace)] = $preference;
        } else {
            // [RFC7240], Section 2: "If any preference is specified more
            // than once, only the first instance is to be considered."
            $value = Pcre::grep($pattern, $value, \PREG_GREP_INVERT);
            array_unshift($value, $preference);
        }
        return $curler->setHeader(HttpHeader::PREFER, $value);
    }

    public function getPage($data, Curler $curler, ?ICurlerPage $previous = null): ICurlerPage
    {
        $prefix = $this->Prefix
            ?: (($curler->ResponseHeadersByName['odata-version'] ?? null) == '4.0'
                ? '@odata.'
                : '@');

        return CurlerPageBuilder::build()
            ->entities($data['value'])
            ->curler($curler)
            ->previous($previous)
            ->nextUrl($data[$prefix . 'nextLink'] ?? null)
            ->go();
    }
}
