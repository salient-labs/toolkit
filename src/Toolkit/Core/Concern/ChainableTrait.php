<?php declare(strict_types=1);

namespace Salient\Core\Concern;

use Salient\Contract\Core\Chainable;

/**
 * Implements Chainable
 *
 * @api
 *
 * @phpstan-require-implements Chainable
 */
trait ChainableTrait
{
    /**
     * @inheritDoc
     */
    public function apply(callable $callback)
    {
        return $callback($this);
    }

    /**
     * @inheritDoc
     */
    public function applyIf($condition, ?callable $then = null, ?callable $else = null)
    {
        if (is_callable($condition)) {
            $condition = $condition($this);
        }

        return $condition
            ? ($then === null ? $this : $then($this))
            : ($else === null ? $this : $else($this));
    }

    /**
     * @inheritDoc
     */
    public function applyForEach(iterable $items, callable $callback)
    {
        $instance = $this;
        foreach ($items as $key => $value) {
            $instance = $callback($instance, $value, $key);
        }
        return $instance;
    }
}
