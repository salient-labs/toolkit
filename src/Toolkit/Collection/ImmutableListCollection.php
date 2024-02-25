<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Core\Contract\Immutable;

/**
 * An immutable array-like list of items
 *
 * @template TValue
 *
 * @implements ListInterface<TValue>
 */
final class ImmutableListCollection implements ListInterface, Immutable
{
    /** @use ImmutableListTrait<TValue> */
    use ImmutableListTrait;
}
