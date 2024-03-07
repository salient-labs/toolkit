<?php declare(strict_types=1);

namespace Salient\Contract\Collection;

/**
 * An array-like list of items
 *
 * @api
 *
 * @template TValue
 *
 * @extends CollectionInterface<int,TValue>
 */
interface ListInterface extends CollectionInterface
{
    /**
     * Add an item
     *
     * @param TValue $value
     * @return static
     */
    public function add($value);

    /**
     * Replace an item with a given key
     *
     * @param int $key
     * @param TValue $value
     * @return static
     */
    public function set($key, $value);

    /**
     * Push one or more items onto the end of the list
     *
     * @param TValue ...$item
     * @return static
     */
    public function push(...$item);

    /**
     * Add one or more items to the beginning of the list
     *
     * Items are prepended in one operation and stay in the given order.
     *
     * @param TValue ...$item
     * @return static
     */
    public function unshift(...$item);
}
