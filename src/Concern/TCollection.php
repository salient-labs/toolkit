<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IComparable;
use Lkrms\Exception\InvalidArgumentException;
use ArrayIterator;
use ReturnTypeWillChange;
use Traversable;

/**
 * Implements ICollection and Arrayable
 *
 * Unless otherwise noted, {@see TCollection} methods operate on one instance of
 * the class. Immutable classes should use {@see TImmutableCollection} instead.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @see \Lkrms\Contract\ICollection
 * @see \Lkrms\Contract\Arrayable
 */
trait TCollection
{
    /**
     * @var array<TKey,TValue>
     */
    protected $Items;

    /**
     * @param static|iterable<TKey,TValue> $items
     */
    public function __construct($items = [])
    {
        $this->Items = $this->getItems($items);
    }

    /**
     * @param TKey $key
     * @param TValue $value
     * @return static
     */
    public function set($key, $value)
    {
        $items = $this->Items;
        $items[$key] = $value;
        return $this->maybeReplaceItems($items);
    }

    /**
     * @param TKey $key
     * @param TValue|null $value
     * @param-out TValue|null $value
     * @return static
     */
    public function unset($key, &$value = null)
    {
        if (!array_key_exists($key, $this->Items)) {
            $value = null;
            return $this;
        }
        $value = $this->Items[$key];
        $clone = $this->clone();
        unset($clone->Items[$key]);
        return $clone;
    }

    /**
     * @param TValue|null $last
     * @param-out TValue|null $last
     * @return static
     */
    public function pop(&$last = null)
    {
        if (!$this->Items) {
            $last = null;
            return $this;
        }
        $clone = $this->clone();
        $last = array_pop($clone->Items);
        return $clone;
    }

    /**
     * @return static A copy of the collection with items sorted by value.
     */
    public function sort()
    {
        $items = $this->Items;
        uasort($items, fn($a, $b) => $this->compareItems($a, $b));
        return $this->maybeReplaceItems($items, true);
    }

    /**
     * @return static A copy of the collection with items in reverse order.
     */
    public function reverse()
    {
        $items = array_reverse($this->Items, true);
        return $this->maybeReplaceItems($items, true);
    }

    /**
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): mixed $callback
     * @return $this
     */
    public function forEach(callable $callback)
    {
        $prev = null;
        $item = null;
        $i = 0;

        // foreach doesn't change the internal array pointer
        foreach ($this->Items as $next) {
            if ($i++) {
                $callback($item, $next, $prev);
                $prev = $item;
            }
            $item = $next;
        }
        if ($i) {
            $callback($item, null, $prev);
        }

        return $this;
    }

    /**
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): bool $callback
     * @return static A copy of the collection with items that satisfy
     * `$callback`.
     */
    public function filter(callable $callback)
    {
        $items = [];
        $prev = null;
        $item = null;
        $key = null;
        $i = 0;

        // foreach doesn't change the internal array pointer
        foreach ($this->Items as $nextKey => $next) {
            if ($i++) {
                if ($callback($item, $next, $prev)) {
                    $items[$key] = $item;
                }
                $prev = $item;
            }
            $item = $next;
            $key = $nextKey;
        }
        if ($i && $callback($item, null, $prev)) {
            $items[$key] = $item;
        }

        return $this->maybeReplaceItems($items, true);
    }

    /**
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): bool $callback
     * @return TValue|null
     */
    public function find(callable $callback)
    {
        $prev = null;
        $item = null;
        $i = 0;

        // foreach doesn't change the internal array pointer
        foreach ($this->Items as $next) {
            if ($i++) {
                if ($callback($item, $next, $prev)) {
                    return $item;
                }
                $prev = $item;
            }
            $item = $next;
        }
        if ($i && $callback($item, null, $prev)) {
            return $item;
        }

        return null;
    }

    /**
     * @return static A copy of the collection with items starting from
     * `$offset`.
     */
    public function slice(int $offset, ?int $length = null)
    {
        $items = array_slice($this->Items, $offset, $length, true);
        return $this->maybeReplaceItems($items, true);
    }

    /**
     * @param TValue $value
     */
    public function has($value, bool $strict = false): bool
    {
        if ($strict) {
            return in_array($value, $this->Items, true);
        }

        foreach ($this->Items as $_item) {
            if (!$this->compareItems($value, $_item)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param TValue $value
     * @return TKey|null
     */
    public function keyOf($value, bool $strict = false)
    {
        if ($strict) {
            $key = array_search($value, $this->Items, true);
            return $key === false
                ? null
                : $key;
        }

        foreach ($this->Items as $key => $_item) {
            if (!$this->compareItems($value, $_item)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param TValue $value
     * @return TValue|null
     */
    public function get($value)
    {
        foreach ($this->Items as $_item) {
            if (!$this->compareItems($value, $_item)) {
                return $_item;
            }
        }
        return null;
    }

    /**
     * @return array<TKey,TValue>
     */
    public function all(): array
    {
        return $this->Items;
    }

    /**
     * @return array<TKey,TValue>
     */
    public function toArray(): array
    {
        return $this->Items;
    }

    /**
     * @return TValue|null
     */
    public function first()
    {
        if (!$this->Items) {
            return null;
        }
        return $this->Items[array_key_first($this->Items)];
    }

    /**
     * @return TValue|null
     */
    public function last()
    {
        if (!$this->Items) {
            return null;
        }
        return $this->Items[array_key_last($this->Items)];
    }

    /**
     * @return TValue|null
     */
    public function nth(int $n)
    {
        if ($n === 0) {
            throw new InvalidArgumentException('Argument #1 ($n) is 1-based, 0 given');
        }

        $keys = array_keys($this->Items);
        if ($n < 0) {
            $keys = array_reverse($keys);
            $n = -$n;
        }

        $key = $keys[$n - 1] ?? null;
        if ($key === null) {
            return null;
        }

        return $this->Items[$key];
    }

    /**
     * @param TValue|null $first
     * @param-out TValue|null $first
     * @return static
     */
    public function shift(&$first = null)
    {
        if (!$this->Items) {
            $first = null;
            return $this;
        }
        $clone = $this->clone();
        $first = array_shift($clone->Items);
        return $clone;
    }

    /**
     * @param static|iterable<TKey,TValue> $items
     * @return static
     */
    public function merge($items)
    {
        $_items = $this->getItems($items);
        $items = $this->Items;
        foreach ($_items as $key => $_item) {
            if (is_int($key)) {
                $items[] = $_item;
                continue;
            }
            $items[$key] = $_item;
        }
        return $this->maybeReplaceItems($items);
    }

    // Implementation of `IteratorAggregate`:

    /**
     * @return Traversable<TKey,TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->Items);
    }

    // Implementation of `ArrayAccess`:

    /**
     * @param TKey $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->Items);
    }

    /**
     * @param TKey $offset
     * @return TValue
     */
    #[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->Items[$offset];
    }

    /**
     * @param TKey|null $offset
     * @param TValue $value
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->Items[] = $value;
            return;
        }
        $this->Items[$offset] = $value;
    }

    /**
     * @param TKey $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->Items[$offset]);
    }

    // Implementation of `Countable`:

    public function count(): int
    {
        return count($this->Items);
    }

    // --

    /**
     * @param static|iterable<TKey,TValue> $items
     * @return array<TKey,TValue>
     */
    protected function getItems($items): array
    {
        if ($items instanceof static) {
            return $items->Items;
        }
        if (is_array($items)) {
            return $items;
        }
        return iterator_to_array($items);
    }

    /**
     * Compare items using IComparable::compare() if implemented
     *
     * @param TValue $a
     * @param TValue $b
     */
    protected function compareItems($a, $b): int
    {
        if (
            $a instanceof IComparable &&
            $b instanceof IComparable
        ) {
            if ($b instanceof $a) {
                return $a->compare($a, $b);
            }
            if ($a instanceof $b) {
                return $b->compare($a, $b);
            }
        }

        return $a <=> $b;
    }

    /**
     * @param array<TKey,TValue> $items
     * @return static
     */
    protected function maybeReplaceItems(array $items, bool $alwaysClone = false)
    {
        if ($items === $this->Items) {
            return $this;
        }
        $clone = $alwaysClone
            ? clone $this
            : $this->clone();
        $clone->Items = $items;
        return $clone;
    }

    /**
     * @return $this
     */
    protected function clone()
    {
        return $this;
    }
}
