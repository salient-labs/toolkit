<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\ListInterface;
use Salient\Contract\Core\Immutable;
use Salient\Core\Concern\HasMutator;
use Salient\Core\Concern\ImmutableArrayAccessTrait;

/**
 * Implements ListInterface for immutable lists
 *
 * Mutable lists should use {@see ListTrait} instead.
 *
 * @see ListInterface
 *
 * @api
 *
 * @template TValue
 *
 * @phpstan-require-implements ListInterface
 * @phpstan-require-implements Immutable
 */
trait ImmutableListTrait
{
    /** @use ListTrait<int,TValue> */
    use ListTrait;
    /** @use ImmutableArrayAccessTrait<int,TValue> */
    use ImmutableArrayAccessTrait {
        ImmutableArrayAccessTrait::offsetSet insteadof ListTrait;
        ImmutableArrayAccessTrait::offsetUnset insteadof ListTrait;
    }
    use HasMutator;

    /**
     * @return static
     */
    protected function maybeClone()
    {
        return $this->clone();
    }
}
