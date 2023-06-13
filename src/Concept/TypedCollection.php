<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\TCollection;
use Lkrms\Contract\ICollection;
use Lkrms\Contract\IImmutable;
use LogicException;

/**
 * Base class for collections of objects of a given type
 *
 * @template TKey of array-key
 * @template TValue of object
 *
 * @implements ICollection<TKey,TValue>
 */
abstract class TypedCollection implements ICollection, IImmutable
{
    /**
     * @var class-string<TValue>
     */
    protected const ITEM_CLASS = \stdClass::class;

    /**
     * @use TCollection<TKey,TValue>
     */
    use TCollection {
        push as private _push;
        unshift as private _unshift;
        offsetSet as private _offsetSet;
    }

    /**
     * @param TValue[] $items
     */
    public function __construct($items = [])
    {
        foreach ($items as $item) {
            if (!is_object($item) || !is_a($item, static::ITEM_CLASS)) {
                $this->throwItemTypeException($item);
            }
        }
        $this->Items = $items;
    }

    public function push(...$item)
    {
        foreach ($item as $_item) {
            if (!is_object($_item) || !is_a($_item, static::ITEM_CLASS)) {
                $this->throwItemTypeException($_item);
            }
        }
        return $this->_push(...$item);
    }

    public function unshift(...$item)
    {
        foreach ($item as $_item) {
            if (!is_object($_item) || !is_a($_item, static::ITEM_CLASS)) {
                $this->throwItemTypeException($_item);
            }
        }
        return $this->_unshift(...$item);
    }

    public function offsetSet($offset, $value): void
    {
        if (!is_object($value) || !is_a($value, static::ITEM_CLASS)) {
            $this->throwItemTypeException($value);
        }
        $this->_offsetSet($offset, $value);
    }

    /**
     * @param mixed $item
     * @return never
     */
    private function throwItemTypeException($item)
    {
        throw new LogicException(sprintf(
            'Not a subclass of %s: %s',
            static::ITEM_CLASS,
            is_object($item) ? get_class($item) : gettype($item)
        ));
    }
}
