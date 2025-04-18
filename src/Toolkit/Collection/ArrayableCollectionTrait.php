<?php declare(strict_types=1);

namespace Salient\Collection;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 */
trait ArrayableCollectionTrait
{
    /** @use HasItems<TKey,TValue> */
    use HasItems;

    /**
     * @inheritDoc
     */
    public function toArray(bool $preserveKeys = true): array
    {
        return $preserveKeys
            ? $this->Items
            : array_values($this->Items);
    }
}
