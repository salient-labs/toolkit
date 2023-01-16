<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\HasSortableItems;
use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;
use Lkrms\Contract\IComparable;
use UnexpectedValueException;

/**
 * Base class for collections of objects of a particular type
 *
 * @template T of object
 * @implements ICollection<T>
 */
abstract class TypedCollection implements ICollection
{
    /**
     * @use TCollection<T>
     * @use HasSortableItems<T>
     */
    use TCollection, HasSortableItems {
        TCollection::has as private _has;
        TCollection::keyOf as private _keyOf;
        TCollection::get as private _get;
        TCollection::offsetSet as private _offsetSet;
    }

    /**
     * @var class-string<T>
     */
    private $ItemClass;

    /**
     * @var bool
     */
    private $HasComparableItems;

    /**
     * @return class-string<T>
     */
    abstract protected function getItemClass(): string;

    public function __construct()
    {
        $this->ItemClass          = $this->getItemClass();
        $this->HasComparableItems = is_a($this->ItemClass, IComparable::class, true);
    }

    /**
     * @param int|string|null $offset
     * @param T $value
     */
    final public function offsetSet($offset, $value): void
    {
        if (!is_a($value, $this->ItemClass)) {
            throw new UnexpectedValueException('Expected an instance of ' . $this->ItemClass);
        }

        $this->_offsetSet($offset, $value);
    }

    final public function has($item, bool $strict = false): bool
    {
        if (!$this->HasComparableItems) {
            return $this->_has($item, $strict);
        }

        foreach ($this->_Items as $_item) {
            if (!$this->compareItems($item, $_item, $strict)) {
                return true;
            }
        }

        return false;
    }

    final public function keyOf($item, bool $strict = false)
    {
        if (!$this->HasComparableItems) {
            return $this->_keyOf($item, $strict);
        }

        foreach ($this->_Items as $key => $_item) {
            if (!$this->compareItems($item, $_item, $strict)) {
                return $key;
            }
        }

        return false;
    }

    final public function get($item)
    {
        if (!$this->HasComparableItems) {
            return $this->_get($item);
        }

        foreach ($this->_Items as $_item) {
            if (!$this->compareItems($item, $_item)) {
                return $_item;
            }
        }

        return false;
    }

    /**
     * @param T $a
     * @param T $b
     */
    protected function compareItems($a, $b, bool $strict = false): int
    {
        switch (true) {
            case $a instanceof IComparable && is_a($b, get_class($a)):
                return $a->compare($b, $strict);
            case $b instanceof IComparable && is_a($a, get_class($b)):
                return -$b->compare($a, $strict);
            default:
                return $a <=> $b;
        }
    }
}
