<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Concern\HasItems;
use ReturnTypeWillChange;
use RuntimeException;

/**
 * Implements ICollection to provide simple array-like collection objects
 *
 * @see \Lkrms\Contract\ICollection
 * @see \Lkrms\Concept\TypedCollection
 * @template T
 * @psalm-require-implements \Lkrms\Contract\ICollection<T>
 */
trait TCollection
{
    /**
     * @use HasItems<T>
     */
    use HasItems;

    /**
     * @return mixed[]
     * @psalm-return T[]
     */
    final public function toArray(): array
    {
        return array_values($this->_Items);
    }

    /**
     * Return true if an item is in the collection
     *
     * @psalm-param T $item
     */
    final public function has($item, bool $strict = false): bool
    {
        return in_array($item, $this->_Items, $strict);
    }

    // Implementation of `Iterator`:

    /**
     * @return mixed|false
     * @psalm-return T|false
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
     * @psalm-return T
     */
    #[ReturnTypeWillChange]
    final public function offsetGet($offset)
    {
        return $this->_Items[$offset];
    }

    /**
     * @param int|string|null $offset
     * @psalm-param T $value
     */
    final public function offsetSet($offset, $value): void
    {
        if (!is_null($offset))
        {
            throw new RuntimeException("Items cannot be added by key");
        }
        $this->_Items[] = $value;
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
