<?php declare(strict_types=1);

namespace Lkrms\Concept;

/**
 * Base class for fluent interfaces
 *
 */
abstract class FluentInterface
{
    /**
     * Move to the next method in the chain after passing the object to a
     * callback
     *
     * @param callable($this): $this $callback Receives and must return the object.
     * @return $this
     */
    final public function call(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Move to the next method in the chain after conditionally passing the
     * object to a callback
     *
     * @param callable($this): $this $then Receives and must return the object. Called if `$condition` is `true`.
     * @param (callable($this): $this)|null $else Receives and must return the object. Called if `$condition` is `false`.
     * @return $this
     */
    final public function if(bool $condition, callable $then, ?callable $else = null)
    {
        if (!$condition) {
            return $else ? $else($this) : $this;
        }

        return $then($this);
    }

    /**
     * Move to the next method in the chain after passing the object to a
     * callback for each key-value pair in an array
     *
     * @param array|object $array
     * @param callable($this, mixed, int|string): $this $callback Receives and must return the object. Called once per element in `$array`.
     * @return $this
     */
    final public function forEach($array, callable $callback)
    {
        $_this = $this;
        foreach ($array as $key => $value) {
            $_this = $callback($_this, $value, $key);
        }

        return $_this;
    }
}
