<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-require-implements CollectionInterface
 */
trait CollectionTrait
{
    /** @use ReadOnlyCollectionTrait<TKey,TValue> */
    use ReadOnlyCollectionTrait;

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
    public function add($value)
    {
        $items = $this->Items;
        $items[] = $value;
        return $this->replaceItems($items, true);
    }

    /**
     * @inheritDoc
     */
    public function merge($items)
    {
        $items = $this->getItemsArray($items);
        if (!$items) {
            return $this;
        }
        // array_merge() can't be used here because it renumbers numeric keys
        $merged = $this->Items;
        foreach ($items as $key => $item) {
            if (is_int($key)) {
                $merged[] = $item;
                continue;
            }
            $merged[$key] = $item;
        }
        return $this->maybeReplaceItems($merged);
    }

    /**
     * @inheritDoc
     */
    public function sort()
    {
        $items = $this->Items;
        uasort($items, fn($a, $b) => $this->compareItems($a, $b));
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function reverse()
    {
        $items = array_reverse($this->Items, true);
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function map(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $items = [];
        $prev = null;
        $item = null;
        $key = null;

        foreach ($this->Items as $nextKey => $nextValue) {
            $next = $this->getCallbackValue($mode, $nextKey, $nextValue);
            if ($item) {
                /** @var TKey $key */
                $items[$key] = $callback($item, $next, $prev);
                $prev = $item;
            }
            $item = $next;
            $key = $nextKey;
        }
        if ($item) {
            /** @var TKey $key */
            $items[$key] = $callback($item, null, $prev);
        }

        // @phpstan-ignore argument.type, return.type
        return $this->maybeReplaceItems($items, true);
    }

    /**
     * @inheritDoc
     */
    public function filter(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $items = [];
        $prev = null;
        $item = null;
        $key = null;
        $value = null;

        foreach ($this->Items as $nextKey => $nextValue) {
            $next = $this->getCallbackValue($mode, $nextKey, $nextValue);
            if ($item) {
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
        if ($item && $callback($item, null, $prev)) {
            /** @var TKey $key */
            /** @var TValue $value */
            $items[$key] = $value;
        }

        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function only(array $keys)
    {
        $items = array_intersect_key($this->Items, array_flip($keys));
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function onlyIn(array $index)
    {
        $items = array_intersect_key($this->Items, $index);
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function except(array $keys)
    {
        $items = array_diff_key($this->Items, array_flip($keys));
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function exceptIn(array $index)
    {
        $items = array_diff_key($this->Items, $index);
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function slice(int $offset, ?int $length = null)
    {
        $items = array_slice($this->Items, $offset, $length, true);
        return $this->maybeReplaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function push(...$items)
    {
        if (!$items) {
            return $this;
        }
        $_items = $this->Items;
        array_push($_items, ...$items);
        return $this->replaceItems($_items, true);
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
     * @inheritDoc
     */
    public function shift(&$first = null)
    {
        if (!$this->Items) {
            $first = null;
            return $this;
        }
        $items = $this->Items;
        $first = reset($items);
        unset($items[key($items)]);
        return $this->replaceItems($items);
    }

    /**
     * @inheritDoc
     */
    public function unshift(...$items)
    {
        if (!$items) {
            return $this;
        }
        $_items = $this->Items;
        array_unshift($_items, ...$items);
        return $this->replaceItems($_items, true);
    }

    // Partial implementation of `ArrayAccess`:

    /**
     * @param TKey|null $offset
     * @param TValue $value
     */
    public function offsetSet($offset, $value): void
    {
        $items = $this->Items;
        if ($offset === null) {
            $items[] = $value;
            $this->replaceItems($items, false, false);
        } else {
            $items[$offset] = $value;
            $this->maybeReplaceItems($items, false, false);
        }
    }

    /**
     * @param TKey $offset
     */
    public function offsetUnset($offset): void
    {
        $items = $this->Items;
        unset($items[$offset]);
        $this->maybeReplaceItems($items, false, false);
    }

    // --

    /**
     * @param array<TKey,TValue> $items
     * @return static
     */
    protected function maybeReplaceItems(array $items, bool $trustKeys = false, bool $getClone = true)
    {
        if ($items === $this->Items) {
            return $this;
        }
        return $this->replaceItems($items, $trustKeys, $getClone);
    }

    /**
     * @param array<TKey,TValue> $items
     * @return static
     */
    protected function replaceItems(array $items, bool $trustKeys = false, bool $getClone = true)
    {
        $clone = $getClone
            ? clone $this
            : $this;
        $clone->Items = $items;
        $clone->handleItemsReplaced();
        return $clone;
    }

    /**
     * Called when items in the collection are replaced
     */
    protected function handleItemsReplaced(): void {}
}
