<?php declare(strict_types=1);

namespace Salient\Contract\Collection;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 * @template TArrayValue = TValue
 *
 * @extends DictionaryInterface<TKey,TValue,TArrayValue>
 */
interface CollectionInterface extends DictionaryInterface
{
    /**
     * Add an item
     *
     * @param TValue $value
     * @return static<TKey|int,TValue,TArrayValue>
     */
    public function add($value);

    /**
     * Push items onto the end of the collection
     *
     * @param TValue ...$items
     * @return static<TKey|int,TValue,TArrayValue>
     */
    public function push(...$items);

    /**
     * Add items to the beginning of the collection
     *
     * Items are added in one operation and stay in the given order.
     *
     * @param TValue ...$items
     * @return static<TKey|int,TValue,TArrayValue>
     */
    public function unshift(...$items);
}
