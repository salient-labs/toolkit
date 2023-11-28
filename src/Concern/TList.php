<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\Arrayable;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Utility\Inspect;

/**
 * Implements IList
 *
 * Unless otherwise noted, {@see TList} methods operate on one instance of the
 * class. Immutable classes should use {@see TImmutableList} instead.
 *
 * @template TValue
 *
 * @see \Lkrms\Contract\IList
 */
trait TList
{
    /** @use TCollection<int,TValue> */
    use TCollection {
        getItems as private _getItems;
        replaceItems as private _replaceItems;
    }

    /**
     * @param Arrayable<array-key,TValue>|iterable<array-key,TValue> $items
     */
    public function __construct($items = [])
    {
        $this->Items = $this->getItems($items);
    }

    /**
     * @param TValue $value
     * @return static
     */
    public function add($value)
    {
        $items = $this->Items;
        $items[] = $value;
        return $this->maybeReplaceItems($items);
    }

    /**
     * @param int $key
     * @param TValue $value
     * @return static
     */
    public function set($key, $value)
    {
        $this->checkKey($key);
        $items = $this->Items;
        $items[$key] = $value;
        return $this->maybeReplaceItems($items);
    }

    /**
     * @param Arrayable<array-key,TValue>|iterable<array-key,TValue> $items
     * @return static
     */
    public function merge($items)
    {
        $_items = $this->getItems($items);
        if (!$_items) {
            return $this;
        }
        $items = array_merge($this->Items, $_items);
        return $this->maybeReplaceItems($items);
    }

    /**
     * @param TValue ...$item
     * @return static
     */
    public function push(...$item)
    {
        if (!$item) {
            return $this;
        }
        $clone = $this->maybeClone();
        array_push($clone->Items, ...$item);
        return $clone;
    }

    /**
     * @param TValue ...$item
     * @return static
     */
    public function unshift(...$item)
    {
        if (!$item) {
            return $this;
        }
        $clone = $this->maybeClone();
        array_unshift($clone->Items, ...$item);
        return $clone;
    }

    /**
     * @param int|null $offset
     * @param TValue $value
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->Items[] = $value;
            return;
        }
        $this->checkKey($offset, '$offset');
        $this->Items[$offset] = $value;
    }

    /**
     * @param int $offset
     */
    public function offsetUnset($offset): void
    {
        if (
            !array_key_exists($offset, $this->Items) ||
            $offset === array_key_last($this->Items)
        ) {
            unset($this->Items[$offset]);
            return;
        }
        $items = $this->Items;
        unset($items[$offset]);
        $this->Items = array_values($items);
    }

    /**
     * @param Arrayable<array-key,TValue>|iterable<array-key,TValue> $items
     * @return list<TValue>
     */
    protected function getItems($items): array
    {
        return array_values($this->_getItems($items));
    }

    /**
     * @param array<int,TValue> $items
     * @return static
     */
    protected function replaceItems(array $items, bool $alwaysClone = false)
    {
        $key = array_key_last($items);
        if ($key === null || $key === count($items) - 1) {
            return $this->_replaceItems($items, $alwaysClone);
        }
        return $this->_replaceItems(array_values($items), $alwaysClone);
    }

    /**
     * @param int $key
     * @throws InvalidArgumentException if `$key` is not an integer, or does not
     * resolve to an existing item and is not the next numeric key in the list.
     */
    private function checkKey($key, string $argument = '$key'): void
    {
        if (!is_int($key)) {
            throw new InvalidArgumentException(sprintf(
                'Argument #1 (%s) must be of type int, %s given',
                $argument,
                Inspect::getType($key),
            ));
        }
        if (
            !array_key_exists($key, $this->Items) &&
            $key !== count($this->Items)
        ) {
            throw new InvalidArgumentException(sprintf(
                'Item cannot be added with key: %d',
                $key,
            ));
        }
    }
}
