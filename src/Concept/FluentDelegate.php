<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IFluentDelegate;
use Lkrms\Support\FluentDelegator;

/**
 * Base class for fluent interfaces that use the delegation pattern
 *
 */
abstract class FluentDelegate implements IFluentDelegate
{
    final public function apply(callable $callback, array $parameters = [])
    {
        return $callback($this, ...$parameters);
    }

    /**
     * @return FluentDelegator<$this>
     */
    final public function if($condition)
    {
        if (is_callable($condition)) {
            $condition = $condition($this);
        }
        return FluentDelegator::withDelegate($this, !$condition);
    }
}
