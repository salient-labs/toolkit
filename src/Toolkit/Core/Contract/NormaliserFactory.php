<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Closure;

/**
 * @api
 */
interface NormaliserFactory
{
    /**
     * Get a closure that normalises the name of a property of the class
     *
     * Arguments after `$name` may be ignored. If `$greedy` is honoured, it
     * should be `true` by default.
     *
     * @return Closure(string $name, bool $greedy=, string...$hints): string
     */
    public static function getNormaliser(): Closure;
}
