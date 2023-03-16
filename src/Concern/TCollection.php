<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Concern\HasItems;
use LogicException;
use ReturnTypeWillChange;

/**
 * Implements ICollection to provide array-like objects
 *
 * @template T
 * @psalm-require-implements \Lkrms\Contract\ICollection<T>
 * @see \Lkrms\Contract\ICollection
 * @see \Lkrms\Concept\TypedCollection
 */
trait TCollection
{
    /**
     * @use HasItems<T>
     */
    use HasItems;

    /**
     * @param callable $callback
     * ```php
     * fn(T $item, ?T $nextItem, ?T $prevItem): void
     * ```
     * @phpstan-param callable(T, ?T, ?T): void $callback
     * @return $this
     */
    final public function forEach(callable $callback)
    {
        $items   = $this->_Items;
        $items[] = null;

        $prev = null;
        $item = null;
        $i    = -1;
        foreach ($items as $next) {
            if ($i++ > -1) {
                $callback($item, $next, $prev);
                $prev = $item;
            }
            $item = $next;
        }

        return $this;
    }

    /**
     * @param callable $callback
     * ```php
     * fn(T $item, ?T $nextItem, ?T $prevItem): bool
     * ```
     * @phpstan-param callable(T, ?T, ?T): bool $callback
     * @return static
     */
    final public function filter(callable $callback)
    {
        $clone         = clone $this;
        $clone->_Items = [];

        $items   = $this->_Items;
        $items[] = null;

        $prev = null;
        $item = null;
        $i    = -1;
        foreach ($items as $next) {
            if ($i++ > -1) {
                if ($callback($item, $next, $prev)) {
                    $clone->_Items[] = $item;
                }
                $prev = $item;
            }
            $item = $next;
        }

        return $clone;
    }

    /**
     * @param callable $callback
     * ```php
     * fn(T $item, ?T $nextItem, ?T $prevItem): bool
     * ```
     * @phpstan-param callable(T, ?T, ?T): bool $callback
     * @return T|false
     */
    final public function find(callable $callback)
    {
        $items   = $this->_Items;
        $items[] = null;

        $prev = null;
        $item = null;
        $i    = -1;
        foreach ($items as $next) {
            if ($i++ > -1) {
                if ($callback($item, $next, $prev)) {
                    return $item;
                }
                $prev = $item;
            }
            $item = $next;
        }

        return false;
    }

    /**
     * @param T $item
     */
    final public function has($item, bool $strict = false): bool
    {
        return in_array($item, $this->_Items, $strict);
    }

    /**
     * @param T $item
     * @return int|string|false
     */
    final public function keyOf($item, bool $strict = false)
    {
        return array_search($item, $this->_Items, $strict);
    }

    /**
     * @param T $item
     * @return T|false
     */
    final public function get($item)
    {
        if (($key = array_search($item, $this->_Items)) === false) {
            return false;
        }

        return $this->_Items[$key];
    }

    /**
     * @return T[]
     */
    final public function toArray(bool $preserveKeys = true): array
    {
        return $preserveKeys
            ? $this->_Items
            : array_values($this->_Items);
    }

    /**
     * @return T|false
     */
    final public function first()
    {
        $copy = $this->_Items;

        return reset($copy);
    }

    /**
     * @return T|false
     */
    final public function last()
    {
        $copy = $this->_Items;

        return end($copy);
    }

    /**
     * @return T|false
     */
    final public function nth(int $n)
    {
        if ($n === 0) {
            throw new LogicException('Argument #1 ($n) is 1-based, 0 given');
        }
        $copy = $this->_Items;
        $keys = array_keys($copy);
        if ($n < 0) {
            $keys = array_reverse($keys);
            $n    = -$n;
        }
        $key = $keys[$n - 1] ?? null;
        if (is_null($key)) {
            return false;
        }

        return $copy[$key];
    }

    /**
     * @return T|false
     */
    final public function shift()
    {
        $item = array_shift($this->_Items);

        return is_null($item)
            ? false
            : $item;
    }

    // Implementation of `Iterator`:

    /**
     * @return T|false
     */
    #[ReturnTypeWillChange]
    final public function current()
    {
        return current($this->_Items);
    }

    /**
     * @return int|string|null
     */
    #[ReturnTypeWillChange]
    final public function key()
    {
        return key($this->_Items);
    }

    final public function next(): void
    {
        next($this->_Items);
    }

    final public function rewind(): void
    {
        reset($this->_Items);
    }

    final public function valid(): bool
    {
        return !is_null(key($this->_Items));
    }

    // Implementation of `ArrayAccess`:

    /**
     * @param int|string|null $offset
     */
    final public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->_Items);
    }

    /**
     * @param int|string|null $offset
     * @return T
     */
    #[ReturnTypeWillChange]
    final public function offsetGet($offset)
    {
        return $this->_Items[$offset];
    }

    /**
     * @param int|string|null $offset
     * @param T $value
     */
    final public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->_Items[] = $value;

            return;
        }
        $this->_Items[$offset] = $value;
    }

    /**
     * @param int|string|null $offset
     */
    final public function offsetUnset($offset): void
    {
        unset($this->_Items[$offset]);
    }

    // Implementation of `Countable`:

    final public function count(): int
    {
        return count($this->_Items);
    }
}
