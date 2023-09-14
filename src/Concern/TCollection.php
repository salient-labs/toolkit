<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IComparable;
use LogicException;
use ReturnTypeWillChange;

/**
 * Implements ICollection to provide a flexible array-like collection of values
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @see \Lkrms\Contract\ICollection
 */
trait TCollection
{
    use HasMutator;

    /**
     * @var TValue[]
     */
    private $Items;

    /**
     * @param TValue[] $items
     */
    public function __construct($items = [])
    {
        $this->Items = $items;
    }

    /**
     * Push one or more items onto the end of the collection
     *
     * {@inheritDoc}
     *
     * Returns a new instance of the collection.
     *
     * @param TValue ...$item
     * @return $this
     */
    public function push(...$item)
    {
        $clone = $this->mutate();
        array_push($clone->Items, ...$item);
        return $clone;
    }

    /**
     * @return TValue|false
     */
    public function pop()
    {
        if (!$this->Items) {
            return false;
        }
        $this->markAsMutant();
        return array_pop($this->Items);
    }

    /**
     * Sort items in the collection
     *
     * {@inheritDoc}
     *
     * Returns a new instance of the collection.
     *
     * @return $this
     */
    public function sort()
    {
        $clone = $this->mutate();
        uasort($clone->Items, fn($a, $b) => $this->compareItems($a, $b));
        return $clone;
    }

    /**
     * Reverse the order of items in the collection
     *
     * {@inheritDoc}
     *
     * Returns a new instance of the collection.
     *
     * @return $this
     */
    public function reverse()
    {
        $clone = $this->mutate();
        $clone->Items = array_reverse($clone->Items, true);
        return $clone;
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
     * Reduce the collection to items that satisfy a callback
     *
     * {@inheritDoc}
     *
     * Returns a new instance of the collection.
     *
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): bool $callback
     * @return $this
     */
    public function filter(callable $callback)
    {
        $clone = $this->mutate();
        $clone->Items = [];

        $prev = null;
        $item = null;
        $key = null;
        $i = 0;

        // foreach doesn't change the internal array pointer
        foreach ($this->Items as $nextKey => $next) {
            if ($i++) {
                if ($callback($item, $next, $prev)) {
                    $clone->Items[$key] = $item;
                }
                $prev = $item;
            }
            $item = $next;
            $key = $nextKey;
        }
        if ($i && $callback($item, null, $prev)) {
            $clone->Items[$key] = $item;
        }

        return $clone;
    }

    /**
     * @param callable(TValue $item, ?TValue $nextItem, ?TValue $prevItem): bool $callback
     * @return TValue|false
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

        return false;
    }

    /**
     * @return $this
     */
    public function slice(int $offset, ?int $length = null)
    {
        $clone = $this->mutate();
        $clone->Items = array_slice($clone->Items, $offset, $length, true);
        return $clone;
    }

    /**
     * @param TValue $item
     */
    public function has($item, bool $strict = false): bool
    {
        if ($strict) {
            return in_array($item, $this->Items, true);
        }

        foreach ($this->Items as $_item) {
            if (!$this->compareItems($item, $_item)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param TValue $item
     * @return TKey|false
     */
    public function keyOf($item, bool $strict = false)
    {
        if ($strict) {
            return array_search($item, $this->Items, true);
        }

        foreach ($this->Items as $key => $_item) {
            if (!$this->compareItems($item, $_item)) {
                return $key;
            }
        }
        return false;
    }

    /**
     * @param TValue $item
     * @return TValue|false
     */
    public function get($item)
    {
        foreach ($this->Items as $_item) {
            if (!$this->compareItems($item, $_item)) {
                return $_item;
            }
        }
        return false;
    }

    /**
     * @return TValue[]
     */
    public function all(): array
    {
        return $this->Items;
    }

    /**
     * @return TValue|false
     */
    public function first()
    {
        $copy = $this->Items;
        return reset($copy);
    }

    /**
     * @return TValue|false
     */
    public function last()
    {
        $copy = $this->Items;
        return end($copy);
    }

    /**
     * @return TValue|false
     */
    public function nth(int $n)
    {
        if ($n === 0) {
            throw new LogicException('Argument #1 ($n) is 1-based, 0 given');
        }
        $keys = array_keys($this->Items);
        if ($n < 0) {
            $keys = array_reverse($keys);
            $n = -$n;
        }
        $key = $keys[$n - 1] ?? null;
        if ($key === null) {
            return false;
        }

        return $this->Items[$key];
    }

    /**
     * @return TValue|false
     */
    public function shift()
    {
        if (!$this->Items) {
            return false;
        }
        $this->markAsMutant();
        return array_shift($this->Items);
    }

    /**
     * Add one or more items to the beginning of the collection
     *
     * {@inheritDoc}
     *
     * Returns a new instance of the collection.
     *
     * @param TValue ...$item
     * @return $this
     */
    public function unshift(...$item)
    {
        $clone = $this->mutate();
        array_unshift($clone->Items, ...$item);
        return $clone;
    }

    // Implementation of `Iterator`:

    /**
     * @return TValue|false
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        return current($this->Items);
    }

    /**
     * @return TKey
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        return key($this->Items);
    }

    public function next(): void
    {
        next($this->Items);
    }

    public function rewind(): void
    {
        reset($this->Items);
    }

    public function valid(): bool
    {
        return key($this->Items) !== null;
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

    /**
     * Compare items using IComparable::compare() if implemented
     *
     * @param TValue $a
     * @param TValue $b
     */
    protected function compareItems($a, $b): int
    {
        if ($a instanceof IComparable && is_a($b, get_class($a))) {
            return $a::compare($a, $b);
        }

        if ($b instanceof IComparable && is_a($a, get_class($b))) {
            return -$b::compare($a, $b);
        }

        return $a <=> $b;
    }
}
