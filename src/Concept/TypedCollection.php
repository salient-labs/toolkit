<?php

namespace Lkrms\Concept;

use Lkrms\Concern\HasSortableItems;
use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;
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
    }

    abstract protected function getItemClass(): string;

    /**
     * @var string|null
     */
    private $ItemClass;

    final public function offsetSet($offset, $value): void
    {
        if (!is_a($value, $this->ItemClass ?: ($this->ItemClass = $this->getItemClass())))
        {
            throw new UnexpectedValueException("Expected an instance of " . $this->ItemClass);
        }

        $this->_offsetSet($offset, $value);
    }

}
