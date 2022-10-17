<?php

declare(strict_types=1);

namespace Lkrms\Concept;

/**
 * Base class for fluent interfaces
 *
 */
abstract class FluentInterface
{
    /**
     * Move to the next method in the chain after applying a conditional
     * callback
     *
     * @return $this
     */
    final public function if(bool $condition, callable $callback)
    {
        if (!$condition)
        {
            return $this;
        }

        return $callback($this);
    }

}
