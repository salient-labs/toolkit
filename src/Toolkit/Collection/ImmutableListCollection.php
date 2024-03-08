<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\ListInterface;
use Salient\Contract\Core\Immutable;

/**
 * An immutable array-like list of items
 *
 * @api
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
