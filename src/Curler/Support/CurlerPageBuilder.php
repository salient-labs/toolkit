<?php declare(strict_types=1);

namespace Lkrms\Curler\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;

/**
 * A fluent interface for creating CurlerPage objects
 *
 * @method static $this build(?IContainer $container = null) Create a new CurlerPageBuilder (syntactic sugar for 'new CurlerPageBuilder()')
 * @method $this entities(array $value) Data extracted from the upstream response
 * @method $this curler(Curler $value) The Curler instance that retrieved the page
 * @method $this previous(?ICurlerPage $value) Pass $value to `$previous` in CurlerPage::__construct()
 * @method $this nextUrl(?string $value) The URL of the next page, including the query component (if any)
 * @method $this isLastPage(?bool $value = true) Set if no more data is available
 * @method $this nextData(?array $value) Data to send in the body of the next request
 * @method $this nextHeaders(?CurlerHeaders $value) Replaces the next request's HTTP headers
 * @method CurlerPage go() Return a new CurlerPage object
 * @method static CurlerPage|null resolve(CurlerPage|CurlerPageBuilder|null $object) Resolve a CurlerPageBuilder or CurlerPage object to a CurlerPage object
 *
 * @uses CurlerPage
 * @lkrms-generate-command lk-util generate builder --static-builder=build --terminator=go --static-resolver=resolve 'Lkrms\Curler\Support\CurlerPage'
 */
final class CurlerPageBuilder extends Builder
{
    /**
     * @internal
     */
    protected static function getClassName(): string
    {
        return CurlerPage::class;
    }
}
