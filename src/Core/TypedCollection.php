<?php

namespace Lkrms\Core;

use ArrayAccess;
use Countable;
use Iterator;
use Lkrms\Container\DI;
use UnexpectedValueException;

/**
 * Base class for collections of objects of a particular type
 */
abstract class TypedCollection implements Iterator, ArrayAccess, Countable
{
    abstract protected function getItemClass(): string;

    protected function compareItems($a, $b): int
    {
        return 0;
    }

    /**
     * @var string|null
     */
    private $ItemClass;

    /**
     * @var mixed[]
     */
    private $Items = [];

    /**
     * @var int
     */
    private $Pointer = 0;

    /**
     * @return mixed|false
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->Items[$this->Pointer] ?? false;
    }

    /**
     * @return int|null
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return array_key_exists($this->Pointer, $this->Items)
            ? $this->Pointer
            : null;
    }

    public function next(): void
    {
        $this->Pointer++;
    }

    public function rewind(): void
    {
        $this->Pointer = 0;
    }

    public function valid(): bool
    {
        return array_key_exists($this->Pointer, $this->Items);
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->Items);
    }

    /**
     * @return mixed
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->Items[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($this->ItemClass))
        {
            $this->ItemClass = $this->getItemClass();
        }

        if (!is_a($value, $this->ItemClass))
        {
            throw new UnexpectedValueException("Expected an instance of: " . $this->ItemClass);
        }

        if (is_null($offset))
        {
            $this->Items[] = $value;
        }
        else
        {
            $this->Items[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->Items[$offset]);
    }

    public function count(): int
    {
        return count($this->Items);
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        return $this->Items;
    }

    /**
     * Get a sorted copy of the collection
     *
     * @return static
     */
    public function sort()
    {
        /** @var static */
        $copy        = DI::get(static::class);
        $copy->Items = $this->Items;
        $copy->_sortItems();
        return $copy;
    }

    private function _sortItems(): void
    {
        usort($this->Items, fn($a, $b) => $this->compareItems($a, $b));
    }

}
