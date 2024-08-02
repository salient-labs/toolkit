<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 */
interface Chainable
{
    /**
     * Move to the next method in the chain after applying a callback to the
     * object
     *
     * @param callable(static): static $callback
     * @return static
     */
    public function apply(callable $callback);

    /**
     * Move to the next method in the chain after applying a conditional
     * callback to the object
     *
     * @param (callable(static): bool)|bool $condition
     * @param (callable(static): static)|null $then Called if `$condition`
     * resolves to `true`.
     * @param (callable(static): static)|null $else Called if `$condition`
     * resolves to `false`.
     * @return static
     */
    public function if($condition, ?callable $then = null, ?callable $else = null);

    /**
     * Move to the next method in the chain after applying a callback to the
     * object with each item in a list
     *
     * @template TKey
     * @template TValue
     *
     * @param iterable<TKey,TValue> $list
     * @param callable(static, TValue, TKey): static $callback
     * @return static
     */
    public function withEach(iterable $list, callable $callback);
}
