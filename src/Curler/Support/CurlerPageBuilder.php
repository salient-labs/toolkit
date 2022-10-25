<?php

declare(strict_types=1);

namespace Lkrms\Curler\Support;

use Lkrms\Concept\Builder;
use Lkrms\Contract\IContainer;
use Lkrms\Curler\Curler;
use Lkrms\Curler\CurlerHeaders;

/**
 * A fluent interface for creating CurlerPage objects
 *
 * @method static $this build(?IContainer $container = null) Create a new CurlerPageBuilder (syntactic sugar for 'new CurlerPageBuilder()')
 * @method $this entities(array $value)
 * @method $this curler(Curler $value)
 * @method $this nextUrl(?string $value)
 * @method $this isLastPage(?bool $value)
 * @method $this nextData(?array $value)
 * @method $this nextHeaders(?CurlerHeaders $value)
 * @method CurlerPage go() Return a new CurlerPage object
 * @method static CurlerPage resolve(CurlerPage|CurlerPageBuilder $object) Resolve a CurlerPageBuilder or CurlerPage object to a CurlerPage object
 *
 * @uses CurlerPage
 * @lkrms-generate-command lk-util generate builder --class='Lkrms\Curler\Support\CurlerPage' --static-builder='build' --terminator='go' --static-resolver='resolve'
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
