<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Closure;

/**
 * Returns a closure that normalises the names of its properties
 */
interface ReturnsNormaliser extends IResolvable
{
    /**
     * Get a closure that normalises a property name
     *
     * Arguments after `$name` may be ignored. If `$greedy` is honoured, it
     * should be `true` by default.
     *
     * @return Closure(string $name, bool $greedy=, string...$hints): string
     */
    public static function normaliser(): Closure;
}
