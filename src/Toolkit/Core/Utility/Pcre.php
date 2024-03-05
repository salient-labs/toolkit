<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Core\Exception\PcreErrorException;
use Salient\Core\AbstractUtility;
use Stringable;

/**
 * Wrappers for PHP's regular expression functions
 */
final class Pcre extends AbstractUtility
{
    /**
     * A wrapper for preg_grep()
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|Stringable|null
     *
     * @param array<TKey,TValue> $array
     * @param int-mask-of<\PREG_GREP_INVERT> $flags
     * @return array<TKey,TValue>
     */
    public static function grep(
        string $pattern,
        array $array,
        int $flags = 0
    ): array {
        $result = preg_grep($pattern, $array, $flags);
        $error = preg_last_error();
        if ($result === false || $error !== \PREG_NO_ERROR) {
            throw new PcreErrorException($error, 'preg_grep', $pattern, $array);
        }
        return $result;
    }

    /**
     * A wrapper for preg_match()
     *
     * @template TFlags of int-mask-of<\PREG_OFFSET_CAPTURE|\PREG_UNMATCHED_AS_NULL>
     *
     * @param mixed[]|null $matches
     * @param-out (
     *     TFlags is 256
     *     ? array<array{string,int}>
     *     : (TFlags is 512
     *         ? array<string|null>
     *         : (TFlags is 768
     *             ? array<array{string|null,int}>
     *             : array<string>
     *         )
     *     )
     * ) $matches
     * @param TFlags $flags
     */
    public static function match(
        string $pattern,
        string $subject,
        ?array &$matches = null,
        int $flags = 0,
        int $offset = 0
    ): int {
        $result = preg_match($pattern, $subject, $matches, $flags, $offset);
        if ($result === false) {
            throw new PcreErrorException(null, 'preg_match', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_match_all()
     *
     * @template TFlags of int-mask-of<\PREG_PATTERN_ORDER|\PREG_SET_ORDER|\PREG_OFFSET_CAPTURE|\PREG_UNMATCHED_AS_NULL>
     *
     * @param mixed[]|null $matches
     * @param-out (
     *     TFlags is 1
     *     ? array<list<string>>
     *     : (TFlags is 2
     *         ? list<array<string>>
     *         : (TFlags is 256|257
     *             ? array<list<array{string,int}>>
     *             : (TFlags is 258
     *                 ? list<array<array{string,int}>>
     *                 : (TFlags is 512|513
     *                     ? array<list<string|null>>
     *                     : (TFlags is 514
     *                         ? list<array<string|null>>
     *                         : (TFlags is 768|769
     *                             ? array<list<array{string|null,int}>>
     *                             : (TFlags is 770
     *                                 ? list<array<array{string|null,int}>>
     *                                 : array<list<string>>
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * ) $matches
     * @param TFlags $flags
     */
    public static function matchAll(
        string $pattern,
        string $subject,
        ?array &$matches = null,
        int $flags = 0,
        int $offset = 0
    ): int {
        $result = preg_match_all($pattern, $subject, $matches, $flags, $offset);
        if ($result === false) {
            throw new PcreErrorException(null, 'preg_match_all', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_replace()
     *
     * @template T of string[]|string
     *
     * @param string[]|string $pattern
     * @param string[]|string $replacement
     * @param T $subject
     * @return T
     */
    public static function replace(
        $pattern,
        $replacement,
        $subject,
        int $limit = -1,
        int &$count = null
    ) {
        $result = preg_replace($pattern, $replacement, $subject, $limit, $count);
        if ($result === null) {
            throw new PcreErrorException(null, 'preg_replace', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_replace_callback()
     *
     * @template T of string[]|string
     * @template TFlags of int-mask-of<\PREG_OFFSET_CAPTURE|\PREG_UNMATCHED_AS_NULL>
     *
     * @param string[]|string $pattern
     * @param callable(array<array-key,string|null>):string $callback
     * @phpstan-param (
     *     TFlags is 256
     *     ? (callable(array<array{string,int}>): string)
     *     : (TFlags is 512
     *         ? (callable(array<string|null>): string)
     *         : (TFlags is 768
     *             ? (callable(array<array{string|null,int}>): string)
     *             : (callable(array<string>): string)
     *         )
     *     )
     * ) $callback
     * @param T $subject
     * @param TFlags $flags
     * @return T
     */
    public static function replaceCallback(
        $pattern,
        callable $callback,
        $subject,
        int $limit = -1,
        int &$count = null,
        int $flags = 0
    ) {
        $result = preg_replace_callback($pattern, $callback, $subject, $limit, $count, $flags);
        if ($result === null) {
            throw new PcreErrorException(null, 'preg_replace_callback', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_replace_callback_array()
     *
     * @template T of string[]|string
     * @template TFlags of int-mask-of<\PREG_OFFSET_CAPTURE|\PREG_UNMATCHED_AS_NULL>
     *
     * @param array<string,callable(array<array-key,string|null>):string> $pattern
     * @phpstan-param (
     *     TFlags is 256
     *     ? array<string,callable(array<array{string,int}>): string>
     *     : (TFlags is 512
     *         ? array<string,callable(array<string|null>): string>
     *         : (TFlags is 768
     *             ? array<string,callable(array<array{string|null,int}>): string>
     *             : array<string,callable(array<string>): string>
     *         )
     *     )
     * ) $pattern
     * @param T $subject
     * @param TFlags $flags
     * @return T
     */
    public static function replaceCallbackArray(
        array $pattern,
        $subject,
        int $limit = -1,
        int &$count = null,
        int $flags = 0
    ) {
        $result = preg_replace_callback_array($pattern, $subject, $limit, $count, $flags);
        if ($result === null) {
            throw new PcreErrorException(null, 'preg_replace_callback_array', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_split()
     *
     * @param int-mask-of<\PREG_SPLIT_NO_EMPTY|\PREG_SPLIT_DELIM_CAPTURE|\PREG_SPLIT_OFFSET_CAPTURE> $flags
     * @return string[]
     */
    public static function split(
        string $pattern,
        string $subject,
        int $limit = -1,
        int $flags = 0
    ): array {
        $result = preg_split($pattern, $subject, $limit, $flags);
        if ($result === false) {
            throw new PcreErrorException(null, 'preg_split', $pattern, $subject);
        }
        return $result;
    }

    /**
     * Enclose a pattern in delimiters
     */
    public static function delimit(string $pattern, string $delimiter = '/'): string
    {
        return sprintf(
            '%s%s%s',
            $delimiter,
            str_replace($delimiter, '\\' . $delimiter, $pattern),
            $delimiter,
        );
    }

    /**
     * Quote characters for use in a character class
     *
     * @param string|null $delimiter The PCRE pattern delimiter to escape.
     * Forward slash ('/') is most commonly used.
     */
    public static function quoteCharacterClass(
        string $characters,
        ?string $delimiter = null
    ): string {
        $orDelimiter = $delimiter === null || $delimiter === ''
            ? ''
            : '|' . preg_quote($delimiter, '/');

        // "All non-alphanumeric characters other than \, -, ^ (at the start)
        // and the terminating ] are non-special in character classes"
        return self::replace("/(?:[]^\\\\-]$orDelimiter)/", '\\\\$0', $characters);
    }
}
