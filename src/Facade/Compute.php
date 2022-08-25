<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Computations;

/**
 * A facade for Computations
 *
 * @method static string closureHash(callable $closure) Generate a hash that uniquely identifies a Closure (or any other callable)
 * @method static string hash(mixed ...$value) Generate a unique non-crypto hash
 * @method static float textDistance(string $string1, string $string2, bool $normalise = true) Returns the Levenshtein distance between two strings relative to the length of the longest string
 * @method static float textSimilarity(string $string1, string $string2, bool $normalise = true) Returns the similarity of two strings relative to the length of the longest string
 * @method static string uuid() Generate a cryptographically secure random UUID
 *
 * @uses Computations
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Computations' --generate='Lkrms\Facade\Compute'
 */
final class Compute extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Computations::class;
    }
}
