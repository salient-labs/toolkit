<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * @api
 */
interface Normalisable
{
    /**
     * Normalise the name of a property of the class
     *
     * Arguments after `$name` may be ignored.
     */
    public static function normalise(string $name, bool $greedy = true, string ...$hints): string;
}
