<?php declare(strict_types=1);

namespace Lkrms\Curler\Support;

use Lkrms\Concept\Builder;
use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Curler;
use Lkrms\Http\Contract\IHttpHeaders;

/**
 * Creates CurlerPage objects via a fluent interface
 *
 * @method $this entities(mixed[] $value) Data extracted from the upstream response
 * @method $this curler(Curler $value) The Curler instance that retrieved the page
 * @method $this previous(?ICurlerPage $value) Pass $value to `$previous` in CurlerPage::__construct()
 * @method $this nextUrl(string|null $value) The URL of the next page, including the query component (if any)
 * @method $this isLastPage(bool|null $value = true) Set if no more data is available
 * @method $this nextData(mixed[]|null $value) Data to send in the body of the next request
 * @method $this nextHeaders(IHttpHeaders|null $value) Replaces the next request's HTTP headers
 *
 * @uses CurlerPage
 *
 * @extends Builder<CurlerPage>
 */
final class CurlerPageBuilder extends Builder
{
    /**
     * @inheritDoc
     */
    protected static function getService(): string
    {
        return CurlerPage::class;
    }
}
