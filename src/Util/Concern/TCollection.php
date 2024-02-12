<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\Arrayable;
use Lkrms\Contract\ICollection;

/**
 * Implements ICollection
 *
 * Unless otherwise noted, {@see TCollection} methods operate on one instance of
 * the class. Immutable classes should use {@see TImmutableCollection} instead.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @see ICollection
 */
trait TCollection
{
    /** @use TReadableCollection<TKey,TValue> */
    use TReadableCollection;

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
     * @return static
     */
    public function unset($key)
    {
        if (!array_key_exists($key, $this->Items)) {
            return $this;
        }
        $items = $this->Items;
        unset($items[$key]);
        return $this->replaceItems($items);
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
        $items = $this->Items;
        $last = array_pop($items);
        return $this->replaceItems($items);
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
     * @param ((callable(TValue, TValue|null $nextValue, TValue|null $prevValue): bool)|(callable(TKey, TKey|null $nextKey, TKey|null $prevKey): bool)|(callable(array<TKey,TValue>, array<TKey,TValue>|null $nextItem, array<TKey,TValue>|null $prevItem): bool)) $callback
     * @param ICollection::CALLBACK_USE_* $mode
     * @return static A copy of the collection with items that satisfy `$callback`.
     */
    public function filter(callable $callback, int $mode = ICollection::CALLBACK_USE_VALUE)
    {
        $items = [];
        $prev = null;
        $item = null;
        $key = null;
        $value = null;
        $i = 0;

        foreach ($this->Items as $nextKey => $nextValue) {
            $next = $mode === ICollection::CALLBACK_USE_KEY
                ? $nextKey
                : ($mode === ICollection::CALLBACK_USE_BOTH
                    ? [$nextKey => $nextValue]
                    : $nextValue);
            if ($i++) {
                if ($callback($item, $next, $prev)) {
                    $items[$key] = $value;
                }
                $prev = $item;
            }
            $item = $next;
            $key = $nextKey;
            $value = $nextValue;
        }
        if ($i && $callback($item, null, $prev)) {
            $items[$key] = $value;
        }

        return $this->maybeReplaceItems($items, true);
    }

    /**
     * @param TKey[] $keys
     * @return static A copy of the collection with items that have keys in
     * `$keys`.
     */
    public function only(array $keys)
    {
        return $this->maybeReplaceItems(
            array_intersect_key($this->Items, array_flip($keys)),
            true
        );
    }

    /**
     * @param array<TKey,true> $index
     * @return static A copy of the collection with items that have keys in
     * `$index`.
     */
    public function onlyIn(array $index)
    {
        return $this->maybeReplaceItems(
            array_intersect_key($this->Items, $index),
            true
        );
    }

    /**
     * @param TKey[] $keys
     * @return static A copy of the collection with items that have keys not in
     * `$keys`.
     */
    public function except(array $keys)
    {
        return $this->maybeReplaceItems(
            array_diff_key($this->Items, array_flip($keys)),
            true
        );
    }

    /**
     * @param array<TKey,true> $index
     * @return static A copy of the collection with items that have keys not in
     * `$index`.
     */
    public function exceptIn(array $index)
    {
        return $this->maybeReplaceItems(
            array_diff_key($this->Items, $index),
            true
        );
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
        $items = $this->Items;
        $first = array_shift($items);
        return $this->replaceItems($items);
    }

    /**
     * @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $items
     * @return static
     */
    public function merge($items)
    {
        $_items = $this->getItems($items);
        if (!$_items) {
            return $this;
        }
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

    // Partial implementation of `ArrayAccess`:

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

    // --

    /**
     * @param array<TKey,TValue> $items
     * @return static
     */
    protected function maybeReplaceItems(array $items, bool $alwaysClone = false)
    {
        if ($items === $this->Items) {
            return $this;
        }
        return $this->replaceItems($items, $alwaysClone);
    }

    /**
     * @param array<TKey,TValue> $items
     * @return static
     */
    protected function replaceItems(array $items, bool $alwaysClone = false)
    {
        $clone = $alwaysClone
            ? clone $this
            : $this->maybeClone();
        $clone->Items = $items;
        return $clone;
    }

    /**
     * @return $this
     */
    protected function maybeClone()
    {
        return $this;
    }
}
