<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 * @template TKeyless
 *
 * @phpstan-require-implements CollectionInterface
 */
trait CollectionTrait
{
    /** @use DictionaryTrait<TKey,TValue> */
    use DictionaryTrait;

    /**
     * @inheritDoc
     */
    public function add($value)
    {
        $items = $this->Items;
        $items[] = $value;
        /** @var TKeyless */
        return $this->replaceItems($items, true);
    }

    /**
     * @inheritDoc
     */
    public function push(...$items)
    {
        if (!$items) {
            /** @var TKeyless */
            return $this;
        }
        $_items = $this->Items;
        array_push($_items, ...$items);
        /** @var TKeyless */
        return $this->replaceItems($_items, true);
    }

    /**
     * @inheritDoc
     */
    public function unshift(...$items)
    {
        if (!$items) {
            /** @var TKeyless */
            return $this;
        }
        $_items = $this->Items;
        array_unshift($_items, ...$items);
        /** @var TKeyless */
        return $this->replaceItems($_items, true);
    }
}
