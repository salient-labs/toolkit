<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Lkrms\Contract\Arrayable;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\AbstractUtility;
use Closure;
use Countable;
use Traversable;

/**
 * Inflect English words
 *
 * @api
 */
final class Inflect extends AbstractUtility
{
    /**
     * Inflect placeholders in a string in the singular if a range covers 1
     * value, or in the plural otherwise
     *
     * For example:
     *
     * ```php
     * <?php
     * $message = Inflect::formatRange($from, $to, '{{#:on:from}} {{#:line}} {{#}}');
     * ```
     *
     * The word used between `$from` and `$to` (default: `to`) can be given
     * explicitly using the following syntax:
     *
     * ```php
     * <?php
     * $message = Inflect::formatRange($from, $to, '{{#:at:between}} {{#:value}} {{#:#:and}}');
     * ```
     *
     * @see Inflect::format()
     *
     * @param int|float $from
     * @param int|float $to
     * @param mixed ...$values Passed to {@see sprintf()} with the inflected
     * string if given.
     */
    public static function formatRange($from, $to, string $format, ...$values): string
    {
        if (is_float($from) xor is_float($to)) {
            throw new InvalidArgumentException('$from and $to must be of the same type');
        }

        $singular = $from === $to;
        $zero = $singular && $from === 0;
        $one = $singular && $from === 1;
        $count = $singular ? ($zero ? 0 : 1) : 2;

        $callback = $singular
            ? fn(): string =>
                (string) $from
            : fn(?string $pluralWord): string =>
                sprintf('%s %s %s', $from, $pluralWord ?? 'to', $to);

        if ($zero) {
            $no = 'no';
        }

        $replace = [
            '#' => $callback,
            '' => $callback,
        ];

        if ($zero || $one) {
            $replace += [
                'no' => $no ?? $callback,
                'a' => $no ?? 'a',
                'an' => $no ?? 'an',
            ];
        } else {
            $replace += [
                'no' => $callback,
                'a' => $callback,
                'an' => $callback,
            ];
        }

        return self::doFormat($count, $format, $replace, false, ...$values);
    }

    /**
     * Inflect placeholders in a string in the singular if a count is 1, or in
     * the plural otherwise
     *
     * For example:
     *
     * ```php
     * <?php
     * $message = Inflect::format($count, '{{#}} {{#:entry}} {{#:was}} processed');
     * ```
     *
     * The following words are recognised:
     *
     * - `#` (unconditionally replaced with a number)
     * - `no` (replaced with a number if `$count` is not `0`)
     * - `a`, `an` (replaced with a number if `$count` is plural, `no` if
     *   `$count` is `0`)
     * - `are` / `is` (inflected)
     * - `has` / `have` (inflected)
     * - `was` / `were` (inflected)
     *
     * Other words are inflected by {@see Inflect::plural()} if `$count` is a
     * value other than `1`, or used without inflection otherwise.
     *
     * The plural form of a word can be given explicitly using the following
     * syntax:
     *
     * ```php
     * <?php
     * '{{#:matrix:matrices}}';
     * ```
     *
     * @param Traversable<array-key,mixed>|Arrayable<array-key,mixed>|Countable|array<array-key,mixed>|int $count
     * @param mixed ...$values Passed to {@see sprintf()} with the inflected
     * string if given.
     */
    public static function format($count, string $format, ...$values): string
    {
        return self::doFormat(Get::count($count), $format, [], false, ...$values);
    }

    /**
     * Inflect placeholders in a string in the singular if a count is 0 or 1, or
     * in the plural otherwise
     *
     * @param Traversable<array-key,mixed>|Arrayable<array-key,mixed>|Countable|array<array-key,mixed>|int $count
     * @param mixed ...$values Passed to {@see sprintf()} with the inflected
     * string if given.
     */
    public static function formatWithSingularZero($count, string $format, ...$values): string
    {
        return self::doFormat(Get::count($count), $format, [], true, ...$values);
    }

    /**
     * @param array<string,(Closure(string|null): string)|string> $replace
     * @param mixed ...$values
     */
    private static function doFormat(int $count, string $format, array $replace, bool $zeroIsSingular, ...$values): string
    {
        $zero = $count === 0;
        $singular = $count === 1 || ($zero && $zeroIsSingular);

        if ($zero) {
            $no = 'no';
        }

        $replace = array_replace($singular
            ? [
                '#' => (string) $count,
                '' => $no ?? (string) $count,
                'no' => $no ?? (string) $count,
                'a' => $no ?? 'a',
                'an' => $no ?? 'an',
                'are' => 'is',
                'is' => 'is',
                'has' => 'has',
                'have' => 'has',
                'was' => 'was',
                'were' => 'was',
            ]
            : [
                '#' => (string) $count,
                '' => (string) $count,
                'no' => $no ?? (string) $count,
                'a' => $no ?? (string) $count,
                'an' => $no ?? (string) $count,
                'are' => 'are',
                'is' => 'are',
                'has' => 'have',
                'have' => 'have',
                'was' => 'were',
                'were' => 'were',
            ], $replace);

        $format = Pcre::replaceCallback(
            '/\{\{#(?::(?<word>[-a-z0-9_\h]*+|#)(?::(?<plural_word>[-a-z0-9_\h]*+))?)?\}\}/i',
            function (array $match) use ($singular, $replace): string {
                $word = $match['word'];
                $plural = $match['plural_word'];
                if ($word === '') {
                    return $singular ? '' : (string) $plural;
                }
                $word ??= (string) $word;
                $word = Get::value($replace[Str::lower($word)]
                    ?? ($singular
                        ? $word
                        : ($plural ?? self::plural($word))), $plural);
                return $word === $match['word'] || $match['word'] === null
                    ? $word
                    : Str::matchCase($word, $match['word']);
            },
            $format,
            -1,
            $count,
            \PREG_UNMATCHED_AS_NULL,
        );

        if ($values) {
            return sprintf($format, ...$values);
        }

        return $format;
    }

    /**
     * Get the plural form of a singular noun
     *
     * @todo Implement https://users.monash.edu/~damian/papers/HTML/Plurals.html
     */
    public static function plural(string $word): string
    {
        foreach ([
            '/(sh?|ch|x|z| (?<! \A phot | \A pian | \A hal ) o) \Z/ix' => ['es', 0],
            '/[^aeiou] y \Z/ix' => ['ies', -1],
            '/is \Z/ix' => ['es', -2],
            '/on \Z/ix' => ['a', -2],
        ] as $pattern => [$replace, $offset]) {
            if (Pcre::match($pattern, $word)) {
                if ($offset) {
                    return substr_replace($word, $replace, $offset);
                }
                return $word . $replace;
            }
        }

        return $word . 's';
    }

    /**
     * Get the indefinite article ("a" or "an") to use before a word
     *
     * Ported from PERL module `Lingua::EN::Inflexion`.
     *
     * @link https://metacpan.org/pod/Lingua::EN::Inflexion
     */
    public static function indefinite(string $word): string
    {
        $ordinalAn = '/\A [aefhilmnorsx] -?th \Z/ix';
        $ordinalA = '/\A [bcdgjkpqtuvwyz] -?th \Z/ix';
        $explicitAn = '/\A (?: euler | hour(?!i) | heir | honest | hono )/ix';
        $singleAn = '/\A [aefhilmnorsx] \Z/ix';
        $singleA = '/\A [bcdgjkpqtuvwyz] \Z/ix';

        // Strings of capitals (i.e. abbreviations) that start with a
        // "vowel-sound" consonant followed by another consonant, and which are
        // not likely to be real words
        $abbrevAn = <<<'REGEX'
            / \A (?!
              FJO | [HLMNS]Y. | RY[EO] | SQU |
              ( F[LR]? | [HL] | MN? | N | RH? | S[CHKLMNPTVW]? | X(YL)? ) [AEIOU]
            )
            [FHLMNRSX][A-Z] /xms
            REGEX;

        // English words beginning with "Y" and followed by a consonant
        $initialYAn = '/\A y (?: b[lor] | cl[ea] | fere | gg | p[ios] | rou | tt)/xi';

        // Handle ordinal forms
        if (Pcre::match($ordinalA, $word)) { return 'a'; }
        if (Pcre::match($ordinalAn, $word)) { return 'an'; }

        // Handle special cases
        if (Pcre::match($explicitAn, $word)) { return 'an'; }
        if (Pcre::match($singleAn, $word)) { return 'an'; }
        if (Pcre::match($singleA, $word)) { return 'a'; }

        // Handle abbreviations
        if (Pcre::match($abbrevAn, $word)) { return 'an'; }
        if (Pcre::match('/\A [aefhilmnorsx][.-]/xi', $word)) { return 'an'; }
        if (Pcre::match('/\A [a-z][.-]/xi', $word)) { return 'a'; }

        // Handle consonants
        if (Pcre::match('/\A [^aeiouy] /xi', $word)) { return 'a'; }

        // Handle special vowel-forms
        if (Pcre::match('/\A e [uw] /xi', $word)) { return 'a'; }
        if (Pcre::match('/\A onc?e \b /xi', $word)) { return 'a'; }
        if (Pcre::match('/\A uni (?: [^nmd] | mo) /xi', $word)) { return 'a'; }
        if (Pcre::match('/\A ut[th] /xi', $word)) { return 'an'; }
        if (Pcre::match('/\A u [bcfhjkqrst] [aeiou] /xi', $word)) { return 'a'; }

        // Handle special capitals
        if (Pcre::match('/\A U [NK] [AIEO]? /x', $word)) { return 'a'; }

        // Handle vowels
        if (Pcre::match('/\A [aeiou]/xi', $word)) { return 'an'; }

        // Handle words beginning with "Y"
        if (Pcre::match($initialYAn, $word)) { return 'an'; }

        // Otherwise, guess "a"
        return 'a';
    }
}
