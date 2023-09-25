<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Normalises the names of its properties
 */
interface IResolvable
{
    /**
     * Normalise a property name
     *
     * Arguments after `$name` may be ignored.
     */
    public static function normalise(string $name, bool $greedy = true, string ...$hints): string;
}
