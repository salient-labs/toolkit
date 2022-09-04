<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use ArrayAccess;
use Countable;
use Iterator;

/**
 * Implements Iterator, ArrayAccess and Countable to provide simple array-like
 * collection objects
 *
 * @throws \RuntimeException if an item is added by key.
 */
interface ICollection extends Iterator, ArrayAccess, Countable
{
    /**
     * @return iterable<mixed>
     */
    public function toList(): iterable;

    /**
     * @return mixed[]
     */
    public function toArray(): array;

}
