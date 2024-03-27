<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Comparable;
use Salient\Contract\Core\Jsonable;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Utility\Json;
use ArrayIterator;
use JsonSerializable;
use ReturnTypeWillChange;
use Traversable;

/**
 * Implements CollectionInterface getters
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
trait ReadableCollectionTrait
{
    /**
     * @var array<TKey,TValue>
     */
    protected $Items = [];

    /**
     * @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $items
     */
    public function __construct($items = [])
    {
        $this->Items = $this->getItems($items);
    }

    /**
     * @return static
     */
    public static function empty()
    {
        return new static();
    }

    /**
     * @return static
     */
    public function copy()
    {
        return clone $this;
    }

    /**
     * @template T of TValue|TKey|array<TKey,TValue>
     *
     * @param callable(T, T|null $nextValue, T|null $prevValue): mixed $callback
     * @param CollectionInterface::CALLBACK_USE_* $mode
     * @return $this
     */
    public function forEach(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $prev = null;
        $item = null;
        $i = 0;

        foreach ($this->Items as $nextKey => $nextValue) {
            $next = $mode === CollectionInterface::CALLBACK_USE_KEY
                ? $nextKey
                : ($mode === CollectionInterface::CALLBACK_USE_BOTH
                    ? [$nextKey => $nextValue]
                    : $nextValue);
            if ($i++) {
                /** @var T $item */
                /** @var T $next */
                $callback($item, $next, $prev);
                $prev = $item;
            }
            $item = $next;
        }
        if ($i) {
            /** @var T $item */
            $callback($item, null, $prev);
        }

        return $this;
    }

    /**
     * @template T of TValue|TKey|array<TKey,TValue>
     *
     * @param callable(T, T|null $nextValue, T|null $prevValue): bool $callback
     * @param CollectionInterface::CALLBACK_USE_* $mode
     * @return TValue|null
     */
    public function find(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $prev = null;
        $item = null;
        $value = null;
        $i = 0;

        foreach ($this->Items as $nextKey => $nextValue) {
            $next = $mode === CollectionInterface::CALLBACK_USE_KEY
                ? $nextKey
                : ($mode === CollectionInterface::CALLBACK_USE_BOTH
                    ? [$nextKey => $nextValue]
                    : $nextValue);
            if ($i++) {
                /** @var T $item */
                /** @var T $next */
                if ($callback($item, $next, $prev)) {
                    return $value;
                }
                $prev = $item;
            }
            $item = $next;
            $value = $nextValue;
        }
        /** @var T $item */
        if ($i && $callback($item, null, $prev)) {
            return $value;
        }

        return null;
    }

    /**
     * @param TValue $value
     */
    public function has($value, bool $strict = false): bool
    {
        if ($strict) {
            return in_array($value, $this->Items, true);
        }

        foreach ($this->Items as $item) {
            if (!$this->compareItems($value, $item)) {
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

        foreach ($this->Items as $key => $item) {
            if (!$this->compareItems($value, $item)) {
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
        foreach ($this->Items as $item) {
            if (!$this->compareItems($value, $item)) {
                return $item;
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
     * @return array<TKey,mixed>
     */
    public function toArray(): array
    {
        $array = $this->Items;
        foreach ($array as &$value) {
            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }
        }
        return $array;
    }

    /**
     * @return array<TKey,mixed>
     */
    public function jsonSerialize(): array
    {
        $array = $this->Items;
        foreach ($array as &$value) {
            if ($value instanceof JsonSerializable) {
                $value = $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                $value = Json::parseObjectAsArray($value->toJson());
            } elseif ($value instanceof Arrayable) {
                $value = $value->toArray();
            }
        }
        return $array;
    }

    public function toJson(int $flags = 0): string
    {
        return Json::stringify($this->jsonSerialize(), $flags);
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

    // Implementation of `IteratorAggregate`:

    /**
     * @return Traversable<TKey,TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->Items);
    }

    // Partial implementation of `ArrayAccess`:

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

    // Implementation of `Countable`:

    public function count(): int
    {
        return count($this->Items);
    }

    // --

    /**
     * @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $items
     * @return array<TKey,TValue>
     */
    protected function getItems($items): array
    {
        if ($items instanceof static) {
            return $items->Items;
        }

        if ($items instanceof self) {
            $items = $items->Items;
        } elseif ($items instanceof Arrayable) {
            $items = $items->toArray();
        } elseif (!is_array($items)) {
            $items = iterator_to_array($items);
        }

        /** @var array<TKey,TValue> $items */
        return $this->filterItems($items);
    }

    /**
     * Override to normalise items applied to the collection
     *
     * @param array<TKey,TValue> $items
     * @return array<TKey,TValue>
     */
    protected function filterItems(array $items): array
    {
        return $items;
    }

    /**
     * Compare items using Comparable::compare() if implemented
     *
     * @param TValue $a
     * @param TValue $b
     */
    protected function compareItems($a, $b): int
    {
        if (
            $a instanceof Comparable &&
            $b instanceof Comparable
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
}
