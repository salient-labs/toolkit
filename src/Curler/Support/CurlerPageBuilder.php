<?php declare(strict_types=1);

namespace Lkrms\Curler\Support;

use Lkrms\Concept\Builder;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Curler;

/**
 * Creates CurlerPage objects via a fluent interface
 *
 * @method $this entities(array $value) Data extracted from the upstream response
 * @method $this curler(Curler $value) The Curler instance that retrieved the page
 * @method $this previous(?ICurlerPage $value) Pass $value to `$previous` in CurlerPage::__construct()
 * @method $this nextUrl(string|null $value) The URL of the next page, including the query component (if any)
 * @method $this isLastPage(bool|null $value = true) Set if no more data is available
 * @method $this nextData(array|null $value) Data to send in the body of the next request
 * @method $this nextHeaders(ICurlerHeaders|null $value) Replaces the next request's HTTP headers
 *
 * @uses CurlerPage
 *
 * @extends Builder<CurlerPage>
 */
final class CurlerPageBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return CurlerPage::class;
    }

    /**
     * @internal
     */
    protected static function getTerminators(): array
    {
        return [];
    }
}
