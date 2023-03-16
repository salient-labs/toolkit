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
     * @param callable $callback Receives and must return the object.
     * ```php
     * fn(FluentInterface $object): FluentInterface
     * ```
     * @phpstan-param callable(static): static $callback
     * @return static
     */
    final public function call(callable $callback)
    {
        return $callback($this);
    }

    /**
     * Move to the next method in the chain after conditionally passing the
     * object to a callback
     *
     * @param callable $callback Receives and must return the object. Called if
     * `$condition` is `true`.
     * ```php
     * fn(FluentInterface $object): FluentInterface
     * ```
     * @phpstan-param callable(static): static $callback
     * @return static
     */
    final public function if(bool $condition, callable $callback)
    {
        if (!$condition) {
            return $this;
        }

        return $callback($this);
    }

    /**
     * Move to the next method in the chain after passing the object to a
     * callback for each key-value pair in an array
     *
     * @param array|object $array
     * @param callable $callback Receives and must return the object. Called
     * once per element in `$array`.
     * ```php
     * fn(FluentInterface $object, $value, int|string $key): FluentInterface
     * ```
     * @phpstan-param callable(static, mixed, int|string): static $callback
     * @return static
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
