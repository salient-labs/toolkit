<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Core\Arrayable;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 */
trait ReadOnlyCollectionTrait
{
    /** @use HasItems<TKey,TValue> */
    use HasItems;

    /**
     * @inheritDoc
     */
    public function __construct($items = [])
    {
        $this->Items = $this->getItemsArray($items);
    }

    /**
     * @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $items
     * @return array<TKey,TValue>
     */
    private function getItemsArray($items): array
    {
        $items = $this->getItems($items);
        return is_array($items)
            ? $items
            : iterator_to_array($items);
    }

    /**
     * @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $items
     * @return iterable<TKey,TValue>
     */
    private function getItems($items): iterable
    {
        if ($items instanceof self) {
            /** @var array<TKey,TValue> */
            $items = $items->Items;
        } elseif ($items instanceof Arrayable) {
            /** @var array<TKey,TValue> */
            $items = $items->toArray();
        }
        return $this->filterItems($items);
    }

    /**
     * Override to normalise items applied to the collection
     *
     * @param iterable<TKey,TValue> $items
     * @return iterable<TKey,TValue>
     */
    private function filterItems(iterable $items): iterable
    {
        return $items;
    }
}
