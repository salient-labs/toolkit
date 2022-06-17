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
        foreach ($this->Items as $item)
        {
            yield $item;
        }
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
        if (!is_null($offset))
        {
            throw new RuntimeException("Items cannot be added by key");
        }
        $this->Items[] = $value;
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
