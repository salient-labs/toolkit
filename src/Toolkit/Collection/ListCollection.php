<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\ListInterface;

/**
 * An array-like list of items
 *
 * @api
 *
 * @template TValue
 *
 * @implements ListInterface<TValue>
 */
final class ListCollection implements ListInterface
{
    /** @use ListTrait<int,TValue> */
    use ListTrait;
}
