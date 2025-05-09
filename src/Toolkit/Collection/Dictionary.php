<?php declare(strict_types=1);

namespace Salient\Collection;

use Salient\Contract\Collection\DictionaryInterface;
use IteratorAggregate;

/**
 * @api
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements DictionaryInterface<TKey,TValue,mixed[]>
 * @implements IteratorAggregate<TKey,TValue>
 */
class Dictionary implements DictionaryInterface, IteratorAggregate
{
    /** @use DictionaryTrait<TKey,TValue> */
    use DictionaryTrait;
    /** @use RecursiveArrayableCollectionTrait<TKey,TValue> */
    use RecursiveArrayableCollectionTrait;
}
