<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Concern\HasItems;
use RuntimeException;

/**
 * Implements ICollection to provide simple array-like collection objects
 *
 * To maintain support for PHP 7.4 when PHP 9 enforces compatible return types,
 * `Iterator` and `ArrayAccess` methods with backward-incompatible return types
 * are provided by a separate version-specific trait.
 *
 * @see \Lkrms\Contract\ICollection
 * @see \Lkrms\Concern\Partial\TCollection
 * @see \Lkrms\Concept\TypedCollection
 */
trait TCollection
{
    use HasItems, \Lkrms\Concern\Partial\TCollection;

    /**
     * @return iterable<mixed>
     */
    final public function toList(): iterable
    {
        foreach ($this->_Items as $item)
        {
            yield $item;
        }
    }

    /**
     * @return mixed[]
     */
    final public function toArray(): array
    {
        return array_values($this->_Items);
    }

    /**
     * Return true if an item is in the collection
     *
     */
    final public function has($item, bool $strict = false): bool
    {
        return in_array($item, $this->_Items, $strict);
    }

    // Partial implementation of `Iterator`:

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

    // Partial implementation of `ArrayAccess`:

    final public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->_Items);
    }

    final public function offsetSet($offset, $value): void
    {
        if (!is_null($offset))
        {
            throw new RuntimeException("Items cannot be added by key");
        }
        $this->_Items[] = $value;
    }

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
