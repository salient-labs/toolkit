<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IFluentInterface;

/**
 * Base class for fluent interfaces
 */
abstract class FluentInterface implements IFluentInterface
{
    final public function apply(callable $callback)
    {
        return $callback($this);
    }

    final public function if($condition, ?callable $then = null, ?callable $else = null)
    {
        if (is_callable($condition)) {
            $condition = $condition($this);
        }
        if (!$condition) {
            return $else ? $else($this) : $this;
        }
        return $then ? $then($this) : $this;
    }
}
