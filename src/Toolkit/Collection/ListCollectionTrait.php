<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Core\Arrayable;

/**
 * @api
 *
 * @template TKey of int
 * @template TValue
 * @template TKeyless
 */
trait ListCollectionTrait
{
    /** @use CollectionTrait<int,TValue,TKeyless> */
    use CollectionTrait {
        getItems as doGetItems;
        replaceItems as private doReplaceItems;
    }

    /**
     * @inheritDoc
     */
    public function merge($items)
    {
        $items = $this->getItemsArray($items);
        if (!$items) {
            return $this;
        }
        $merged = array_merge($this->Items, $items);
        return $this->replaceItems($merged, true);
    }

    /**
     * @inheritDoc
     */
    public function shift(&$first = null)
    {
        if (!$this->Items) {
            $first = null;
            return $this;
        }
        $items = $this->Items;
        $first = array_shift($items);
        return $this->replaceItems($items, true);
    }

    /**
     * @param array<int,TValue> $items
     * @return static
     */
    protected function replaceItems(array $items, bool $trustKeys = false, bool $getClone = true)
    {
        if (!$trustKeys) {
            $items = array_values($items);
        }
        return $this->doReplaceItems($items, $trustKeys, $getClone);
    }

    /**
     * @param Arrayable<array-key,TValue>|iterable<array-key,TValue> $items
     * @return iterable<TValue>
     */
    private function getItems($items): iterable
    {
        foreach ($this->doGetItems($items) as $value) {
            yield $value;
        }
    }
}
