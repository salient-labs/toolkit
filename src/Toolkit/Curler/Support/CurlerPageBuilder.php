<?php declare(strict_types=1);

namespace Salient\Curler\Support;

use Salient\Contract\Http\HttpHeadersInterface;
use Salient\Core\AbstractBuilder;
use Salient\Curler\Contract\ICurlerPage;
use Salient\Curler\Curler;

/**
 * A fluent CurlerPage factory
 *
 * @method $this entities(mixed[] $value) Data extracted from the upstream response
 * @method $this curler(Curler $value) The Curler instance that retrieved the page
 * @method $this previous(?ICurlerPage $value) Pass $value to `$previous` in CurlerPage::__construct()
 * @method $this nextUrl(string|null $value) The URL of the next page, including the query component (if any)
 * @method $this isLastPage(bool|null $value = true) Set if no more data is available
 * @method $this nextData(mixed[]|null $value) Data to send in the body of the next request
 * @method $this nextHeaders(HttpHeadersInterface|null $value) Replaces the next request's HTTP headers
 *
 * @extends AbstractBuilder<CurlerPage>
 *
 * @generated
 */
final class CurlerPageBuilder extends AbstractBuilder
{
    /**
     * @internal
     */
    protected static function getService(): string
    {
        return CurlerPage::class;
    }
}
