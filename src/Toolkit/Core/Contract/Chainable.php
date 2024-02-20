<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * @api
 */
interface Chainable
{
    /**
     * Move to the next method in the chain after applying a callback to the
     * object
     *
     * @param callable($this): $this $callback
     * @return $this
     */
    public function apply(callable $callback);

    /**
     * Move to the next method in the chain after applying a conditional
     * callback to the object
     *
     * @param (callable($this): bool)|bool $condition
     * @param (callable($this): $this)|null $then Called if `$condition`
     * resolves to `true`.
     * @param (callable($this): $this)|null $else Called if `$condition`
     * resolves to `false`.
     * @return $this
     */
    public function if($condition, ?callable $then = null, ?callable $else = null);
}
