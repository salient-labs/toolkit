<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\CollectionInterface;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasImmutableProperties;
use Salient\Core\Concern\ImmutableArrayAccessTrait;

/**
 * Implements CollectionInterface for immutable collections
 *
 * Mutable collections should use {@see CollectionTrait} instead.
 *
 * @see CollectionInterface
 *
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-require-implements CollectionInterface
 * @phpstan-require-implements Immutable
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
