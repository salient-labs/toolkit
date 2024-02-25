<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Normalises the names of its properties
 */
interface Normalisable
{
    /**
     * Normalise a property name
     *
     * Arguments after `$name` may be ignored.
     */
    public static function normalise(string $name, bool $greedy = true, string ...$hints): string;
}
