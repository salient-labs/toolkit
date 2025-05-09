<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Core\Arrayable;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 */
trait RecursiveArrayableCollectionTrait
{
    /** @use HasItems<TKey,TValue> */
    use HasItems;

    /**
     * @inheritDoc
     */
    public function toArray(bool $preserveKeys = true): array
    {
        foreach ($this->Items as $key => $value) {
            if ($value instanceof Arrayable) {
                $value = $value->toArray($preserveKeys);
            }
            if ($preserveKeys) {
                $array[$key] = $value;
            } else {
                $array[] = $value;
            }
        }
        return $array ?? [];
    }
}
