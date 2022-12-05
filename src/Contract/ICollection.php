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
 * @template T
 * @extends Iterator<int,T>
 * @extends ArrayAccess<int,T>
 */
interface ICollection extends Iterator, ArrayAccess, Countable
{
    /**
     * @return mixed[]
     * @psalm-return T[]
     */
    public function toArray(): array;

    /**
     * @return mixed|false
     * @psalm-return T|false
     */
    public function first();

    /**
     * @return mixed|false
     * @psalm-return T|false
     */
    public function last();
}
