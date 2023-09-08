<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Closure;

/**
 * Normalises property names
 *
 */
interface IResolvable
{
    /**
     * Return a closure that normalises a property name
     *
     * Arguments after `$name` may be ignored. If `$greedy` is honoured, it
     * should be `true` by default.
     *
     * @return Closure(string $name, bool $greedy=, string...$hints): string
     */
    public static function normaliser(): Closure;

    /**
     * Normalise a property name
     *
     * Arguments after `$name` may be ignored.
     */
    public static function normalise(string $name, bool $greedy = true, string ...$hints): string;
}
