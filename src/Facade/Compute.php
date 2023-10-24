<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\Catalog\CharacterSequence as Char;
use Lkrms\Utility\Computations;
use Stringable;

/**
 * A facade for \Lkrms\Utility\Computations
 *
 * @method static Computations load() Load and return an instance of the underlying Computations class
 * @method static Computations getInstance() Get the underlying Computations instance
 * @method static bool isLoaded() True if an underlying Computations instance has been loaded
 * @method static void unload() Clear the underlying Computations instance
 * @method static string binaryHash(int|float|string|bool|Stringable|null ...$value) Get a unique non-crypto hash in raw binary form
 * @method static string hash(int|float|string|bool|Stringable|null ...$value) Get a unique non-crypto hash in hexadecimal form
 * @method static float ngramSimilarity(string $string1, string $string2, bool $normalise = true, int $size = 2) Get the ngrams shared between two strings relative to the number of ngrams in the longest string (see {@see Computations::ngramSimilarity()})
 * @method static string[] ngrams(string $string, int $size = 2) Get a string's n-grams
 * @method static string randomText(int $length, string $chars = Char::ALPHANUMERIC) Get a cryptographically secure string
 * @method static float textDistance(string $string1, string $string2, bool $normalise = true) Get the Levenshtein distance between two strings relative to the length of the longest string (see {@see Computations::textDistance()})
 * @method static float textSimilarity(string $string1, string $string2, bool $normalise = true) Get the similarity of two strings relative to the length of the longest string (see {@see Computations::textSimilarity()})
 * @method static string uuid(bool $binary = false) Get a cryptographically secure UUID (see {@see Computations::uuid()})
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
