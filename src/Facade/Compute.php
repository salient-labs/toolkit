<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Computations;

/**
 * A facade for \Lkrms\Utility\Computations
 *
 * @method static Computations load() Load and return an instance of the underlying Computations class
 * @method static Computations getInstance() Get the underlying Computations instance
 * @method static bool isLoaded() True if an underlying Computations instance has been loaded
 * @method static void unload() Clear the underlying Computations instance
 * @method static string binaryHash(...$value) Generate a unique non-crypto hash and return raw binary data
 * @method static string hash(...$value) Generate a unique non-crypto hash and return a hexadecimal string
 * @method static string randomText(int $length, string $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789') Generate a cryptographically secure string
 * @method static float textDistance(string $string1, string $string2, bool $normalise = true) Returns the Levenshtein distance between two strings relative to the length of the longest string (see {@see Computations::textDistance()})
 * @method static float textSimilarity(string $string1, string $string2, bool $normalise = true) Returns the similarity of two strings relative to the length of the longest string (see {@see Computations::textSimilarity()})
 * @method static string uuid(bool $binary = false) Generate a cryptographically secure random UUID (see {@see Computations::uuid()})
 *
 * @uses Computations
 *
 * @extends Facade<Computations>
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
