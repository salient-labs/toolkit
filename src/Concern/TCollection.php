<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Concern\HasItems;

/**
 * Implements Iterator, ArrayAccess and Countable to provide a simple array-like
 * collection
 *
 * To maintain support for PHP 7.4 when PHP 9 enforces compatible return types,
 * `Iterator` and `ArrayAccess` methods with backward-incompatible return types
 * are provided by a separate version-specific trait.
 *
 * @see \Lkrms\Concept\TypedCollection
 * @see \Lkrms\Concern\Partial\TCollection
 */
trait TCollection
{
    use HasItems, \Lkrms\Concern\Partial\TCollection;

    /**
     * @return mixed[]
     */
    final public function toArray(): array
    {
        return $this->Items;
    }

    // Partial implementation of `Iterator`:

    final public function next(): void
    {
        next($this->Items);
    }

    final public function rewind(): void
    {
        reset($this->Items);
    }

    final public function valid(): bool
    {
        return !is_null(key($this->Items));
    }

    // Partial implementation of `ArrayAccess`:

    final public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->Items);
    }

    final public function offsetSet($offset, $value): void
    {
        if (is_null($offset))
        {
            $this->Items[] = $value;
        }
        else
        {
            $this->Items[$offset] = $value;
        }
    }

    final public function offsetUnset($offset): void
    {
        unset($this->Items[$offset]);
    }

    // Implementation of `Countable`:

    final public function count(): int
    {
        return count($this->Items);
    }

}
