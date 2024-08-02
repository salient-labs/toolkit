<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Chainable;

/**
 * Implements Chainable
 *
 * @see Chainable
 *
 * @api
 *
 * @phpstan-require-implements Chainable
 */
trait HasChainableMethods
{
    /**
     * @param callable(static): static $callback
     * @return static
     */
    public function apply(callable $callback)
    {
        return $callback($this);
    }

    /**
     * @param (callable(static): bool)|bool $condition
     * @param (callable(static): static)|null $then
     * @param (callable(static): static)|null $else
     * @return static
     */
    public function if($condition, ?callable $then = null, ?callable $else = null)
    {
        if (is_callable($condition)) {
            $condition = $condition($this);
        }
        if (!$condition) {
            return $else === null ? $this : $else($this);
        }
        return $then === null ? $this : $then($this);
    }

    /**
     * @template TKey
     * @template TValue
     *
     * @param iterable<TKey,TValue> $list
     * @param callable(static, TValue, TKey): static $callback
     * @return static
     */
    public function withEach(iterable $list, callable $callback)
    {
        $instance = $this;
        foreach ($list as $key => $value) {
            $instance = $callback($instance, $value, $key);
        }
        return $instance;
    }
}
