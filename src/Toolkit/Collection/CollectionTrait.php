<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;

/**
 * Implements CollectionInterface
 *
 * Unless otherwise noted, {@see CollectionTrait} methods operate on one
 * instance of the class. Immutable collections should use
 * {@see ImmutableCollectionTrait} instead.
 *
 * @see CollectionInterface
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-require-implements CollectionInterface
 */
trait CollectionTrait
{
    /** @use ReadableCollectionTrait<TKey,TValue> */
    use ReadableCollectionTrait;

    /**
     * @inheritDoc
     */
    public function set($key, $value)
    {
        $items = $this->Items;
        $items[$key] = $value;
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @template T of TValue|TKey|array{TKey,TValue}
     *
     * @param callable(T, T|null $next, T|null $prev): bool $callback
     * @return static A copy of the collection with items that satisfy `$callback`.
     */
    public function filter(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $items = [];
        $prev = null;
        $item = null;
        $key = null;
        $value = null;
        $i = 0;

        foreach ($this->Items as $nextKey => $nextValue) {
            $next = $this->getCallbackValue($mode, $nextKey, $nextValue);
            if ($i++) {
                /** @var T $item */
                /** @var T $next */
                if ($callback($item, $next, $prev)) {
                    /** @var TKey $key */
                    /** @var TValue $value */
                    $items[$key] = $value;
                }
                $prev = $item;
            }
            $item = $next;
            $key = $nextKey;
            $value = $nextValue;
        }
        /** @var T $item */
        if ($i && $callback($item, null, $prev)) {
            /** @var TKey $key */
            /** @var TValue $value */
            $items[$key] = $value;
        }

        return $this->maybeReplaceItems($items, true);
    }

    /**
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function merge($items)
    {
        $_items = $this->getItems($items);
        if (!$_items) {
            return $this;
        }
        // array_merge() can't be used here because it renumbers numeric keys
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
