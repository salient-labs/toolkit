<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Comparable;
use Salient\Contract\Core\Jsonable;
use Salient\Utility\Json;
use ArrayIterator;
use InvalidArgumentException;
use JsonSerializable;
use OutOfRangeException;
use ReturnTypeWillChange;
use Traversable;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-require-implements CollectionInterface
 */
trait ReadOnlyCollectionTrait
{
    /** @var array<TKey,TValue> */
    protected $Items = [];

    /**
     * @inheritDoc
     */
    public function __construct($items = [])
    {
        $this->Items = $this->getItemsArray($items);
    }

    /**
     * @inheritDoc
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->Items);
    }

    /**
     * @inheritDoc
     */
    public function get($key)
    {
        if (!array_key_exists($key, $this->Items)) {
            throw new OutOfRangeException(sprintf('Item not found: %s', $key));
        }
        return $this->Items[$key];
    }

    /**
     * @inheritDoc
     */
    public function forEach(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE)
    {
        $prev = null;
        $item = null;

        foreach ($this->Items as $nextKey => $nextValue) {
            $next = $this->getCallbackValue($mode, $nextKey, $nextValue);
            if ($item !== null) {
                $callback($item, $next, $prev);
            }
            $prev = $item;
            $item = $next;
        }
        if ($item !== null) {
            $callback($item, null, $prev);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function find(callable $callback, int $mode = CollectionInterface::CALLBACK_USE_VALUE | CollectionInterface::FIND_VALUE)
    {
        $prev = null;
        $item = null;
        $key = null;
        $value = null;

        foreach ($this->Items as $nextKey => $nextValue) {
            $next = $this->getCallbackValue($mode, $nextKey, $nextValue);
            if ($item !== null) {
                if ($callback($item, $next, $prev)) {
                    /** @var TKey $key */
                    /** @var TValue $value */
                    // @phpstan-ignore return.type
                    return $this->getReturnValue($mode, $key, $value);
                }
            }
            $prev = $item;
            $item = $next;
            $key = $nextKey;
            $value = $nextValue;
        }
        if ($item !== null && $callback($item, null, $prev)) {
            /** @var TKey $key */
            /** @var TValue $value */
            // @phpstan-ignore return.type
            return $this->getReturnValue($mode, $key, $value);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function hasValue($value, bool $strict = false): bool
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function firstOf($value)
    {
        foreach ($this->Items as $item) {
            if (!$this->compareItems($value, $item)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        return $this->Items;
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        foreach ($this->Items as $key => $value) {
            $array[$key] = $value instanceof Arrayable
                ? $value->toArray()
                : $value;
        }
        return $array ?? [];
    }

    /**
     * @return array<TKey,mixed>
     */
    public function jsonSerialize(): array
    {
        foreach ($this->Items as $key => $value) {
            if ($value instanceof JsonSerializable) {
                $array[$key] = $value->jsonSerialize();
            } elseif ($value instanceof Jsonable) {
                $array[$key] = Json::parseObjectAsArray($value->toJson());
            } elseif ($value instanceof Arrayable) {
                $array[$key] = $value->toArray();
            } else {
                $array[$key] = $value;
            }
        }
        return $array ?? [];
    }

    /**
     * @inheritDoc
     */
    public function toJson(int $flags = 0): string
    {
        return Json::stringify($this->jsonSerialize(), $flags);
    }

    /**
     * @inheritDoc
     */
    public function first()
    {
        return $this->Items ? reset($this->Items) : null;
    }

    /**
     * @inheritDoc
     */
    public function last()
    {
        return $this->Items ? end($this->Items) : null;
    }

    /**
     * @inheritDoc
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
    protected function getItemsArray($items): array
    {
        $items = $this->getItems($items);
        return is_array($items)
            ? $items
            : iterator_to_array($items);
    }

    /**
     * @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $items
     * @return iterable<TKey,TValue>
     */
    protected function getItems($items): iterable
    {
        if ($items instanceof self) {
            $items = $items->Items;
        } elseif ($items instanceof Arrayable) {
            $items = $items->toArray();
        }
        // @phpstan-ignore argument.type
        return $this->filterItems($items);
    }

    /**
     * Override to normalise items applied to the collection
     *
     * @param iterable<TKey,TValue> $items
     * @return iterable<TKey,TValue>
     */
    protected function filterItems(iterable $items): iterable
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
            $a instanceof Comparable
            && $b instanceof Comparable
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
     * @param int-mask-of<CollectionInterface::*> $mode
     * @param TKey $key
     * @param TValue $value
     * @return ($mode is 3|11|19 ? array{TKey,TValue} : ($mode is 2|10|18 ? TKey : TValue))
     */
    protected function getCallbackValue(int $mode, $key, $value)
    {
        $mode &= CollectionInterface::CALLBACK_USE_BOTH;
        if ($mode === CollectionInterface::CALLBACK_USE_KEY) {
            return $key;
        }
        return $mode === CollectionInterface::CALLBACK_USE_BOTH
            ? [$key, $value]
            : $value;
    }

    /**
     * @param int-mask-of<CollectionInterface::*> $mode
     * @param TKey $key
     * @param TValue $value
     * @return ($mode is 16|17|18|19 ? TKey : TValue)
     */
    protected function getReturnValue(int $mode, $key, $value)
    {
        return $mode & CollectionInterface::FIND_KEY
            && !($mode & CollectionInterface::FIND_VALUE)
                ? $key
                : $value;
    }
}
