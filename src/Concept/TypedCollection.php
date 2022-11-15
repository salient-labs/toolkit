<?php

namespace Lkrms\Concept;

use Lkrms\Concern\HasSortableItems;
use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;
use Lkrms\Contract\IComparable;
use UnexpectedValueException;

/**
 * Base class for collections of objects of a particular type
 *
 */
abstract class TypedCollection implements ICollection
{
    use TCollection, HasSortableItems
    {
        TCollection::offsetSet as private _offsetSet;
        TCollection::has as private _has;
    }

    /**
     * @var string
     */
    private $ItemClass;

    /**
     * @var bool
     */
    private $HasComparableItems;

    abstract protected function getItemClass(): string;

    public function __construct()
    {
        $this->ItemClass = $this->getItemClass();
        $this->HasComparableItems = is_a($this->ItemClass, IComparable::class, true);
    }

    final public function offsetSet($offset, $value): void
    {
        if (!is_a($value, $this->ItemClass))
        {
            throw new UnexpectedValueException("Expected an instance of " . $this->ItemClass);
        }

        $this->_offsetSet($offset, $value);
    }

    final public function has($item, bool $strict = false): bool
    {
        if (!$this->HasComparableItems)
        {
            return $this->_has($item, $strict);
        }

        foreach ($this->_Items as $_item)
        {
            if (!$this->compareItems($item, $_item, $strict))
            {
                return true;
            }
        }

        return false;
    }

    protected function compareItems($a, $b, bool $strict = false): int
    {
        return $this->HasComparableItems
            ? $this->ItemClass::compare($a, $b, $strict)
            : ($a <=> $b);
    }

}
