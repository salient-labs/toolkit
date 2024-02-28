<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Chainable;

/**
 * Implements Chainable
 *
 * @see Chainable
 */
trait HasChainableMethods
{
    /**
     * @param callable($this): static $callback
     * @return static
     */
    public function apply(callable $callback)
    {
        return $callback($this);
    }

    /**
     * @param (callable($this): bool)|bool $condition
     * @param (callable($this): static)|null $then
     * @param (callable($this): static)|null $else
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
}
