<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Base interface for fluent classes that use the delegation pattern
 *
 */
interface IFluentDelegate
{
    /**
     * Move to the next method in the chain after applying a callback to the
     * object
     *
     * @param callable($this): $this $callback
     * @param mixed[] $parameters
     * @return $this
     */
    public function apply(callable $callback, array $parameters = []);

    /**
     * Move to the next method in the chain if a condition is met
     *
     * If `$condition` resolves to `false`, chained method calls are ignored
     * until {@see HasConditionalDelegate::elseIf()},
     * {@see HasConditionalDelegate::else()} or
     * {@see HasConditionalDelegate::endIf()} are called.
     *
     * Calls to this method should always be followed by a call to
     * {@see HasConditionalDelegate::endIf()}.
     *
     * @param (callable($this): bool)|bool $condition
     * @return HasConditionalDelegate<$this>
     */
    public function if($condition);
}
