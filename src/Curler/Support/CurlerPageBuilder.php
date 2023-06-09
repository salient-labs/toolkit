<?php declare(strict_types=1);

namespace Lkrms\Curler\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Curler\Contract\ICurlerHeaders;
use Lkrms\Curler\Contract\ICurlerPage;
use Lkrms\Curler\Curler;

/**
 * A fluent interface for creating CurlerPage objects
 *
 * @method static $this build(?IContainer $container = null) Create a new CurlerPageBuilder (syntactic sugar for 'new CurlerPageBuilder()')
 * @method $this entities(array $value) Data extracted from the upstream response
 * @method $this curler(Curler $value) The Curler instance that retrieved the page
 * @method $this previous(?ICurlerPage $value) Pass $value to `$previous` in CurlerPage::__construct()
 * @method $this nextUrl(string|null $value) The URL of the next page, including the query component (if any)
 * @method $this isLastPage(bool|null $value = true) Set if no more data is available
 * @method $this nextData(array|null $value) Data to send in the body of the next request
 * @method $this nextHeaders(ICurlerHeaders|null $value) Replaces the next request's HTTP headers
 * @method mixed get(string $name) The value of $name if applied to the unresolved CurlerPage by calling $name(), otherwise null
 * @method bool isset(string $name) True if a value for $name has been applied to the unresolved CurlerPage by calling $name()
 * @method CurlerPage go() Get a new CurlerPage object
 * @method static CurlerPage resolve(CurlerPage|CurlerPageBuilder $object) Resolve a CurlerPageBuilder or CurlerPage object to a CurlerPage object
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
    protected static function getClassName(): string
    {
        return CurlerPage::class;
    }
}
