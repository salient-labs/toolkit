<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Concern\ImmutableArrayAccessTrait;

/**
 * Implements CollectionInterface for immutable classes
 *
 * Mutable classes should use {@see CollectionTrait} instead.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @see CollectionInterface
 */
trait ImmutableCollectionTrait
{
    /** @use CollectionTrait<TKey,TValue> */
    use CollectionTrait;
    /** @use ImmutableArrayAccessTrait<TKey,TValue> */
    use ImmutableArrayAccessTrait {
        ImmutableArrayAccessTrait::offsetSet insteadof CollectionTrait;
        ImmutableArrayAccessTrait::offsetUnset insteadof CollectionTrait;
    }
    use HasImmutableProperties;

    /**
     * @return static
     */
    protected function maybeClone()
    {
        return $this->clone();
    }
}
