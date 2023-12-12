<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Exception\PcreErrorException;

/**
 * preg_* function wrappers that throw an exception on failure
 */
final class Pcre extends Utility
{
    /**
     * A wrapper for preg_grep()
     *
     * @template TKey of array-key
     * @template TValue of int|float|string|bool|\Stringable|null
     *
     * @param array<TKey,TValue> $array
     * @param int-mask<0,\PREG_GREP_INVERT> $flags
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
     * @param mixed[] $matches
     * @param int-mask<0,\PREG_OFFSET_CAPTURE,\PREG_UNMATCHED_AS_NULL> $flags
     */
    public static function match(
        string $pattern,
        string $subject,
        array &$matches = null,
        int $flags = 0,
        int $offset = 0
    ): int {
        $result = preg_match($pattern, $subject, $matches, $flags, $offset);
        if ($result === false) {
            $error = preg_last_error();
            throw new PcreErrorException($error, 'preg_match', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_match_all()
     *
     * @param mixed[] $matches
     * @param int-mask<0,\PREG_PATTERN_ORDER,\PREG_SET_ORDER,\PREG_OFFSET_CAPTURE,\PREG_UNMATCHED_AS_NULL> $flags
     */
    public static function matchAll(
        string $pattern,
        string $subject,
        array &$matches = null,
        int $flags = 0,
        int $offset = 0
    ): int {
        $result = preg_match_all($pattern, $subject, $matches, $flags, $offset);
        if ($result === false) {
            $error = preg_last_error();
            throw new PcreErrorException($error, 'preg_match_all', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_replace()
     *
     * @template T of string[]|string
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
            $error = preg_last_error();
            throw new PcreErrorException($error, 'preg_replace', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_replace_callback()
     *
     * @template T of string[]|string
     * @param string[]|string $pattern
     * @param callable(array<array-key,string|null>):string $callback
     * @param T $subject
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
            $error = preg_last_error();
            throw new PcreErrorException($error, 'preg_replace_callback', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_replace_callback_array()
     *
     * @template T of string[]|string
     * @param array<string,callable(array<array-key,string|null>):string> $pattern
     * @param T $subject
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
            $error = preg_last_error();
            throw new PcreErrorException($error, 'preg_replace_callback_array', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_split()
     *
     * @param int-mask<0,\PREG_SPLIT_NO_EMPTY,\PREG_SPLIT_DELIM_CAPTURE,\PREG_SPLIT_OFFSET_CAPTURE> $flags
     * @return mixed[]
     */
    public static function split(
        string $pattern,
        string $subject,
        int $limit = -1,
        int $flags = 0
    ): array {
        $result = preg_split($pattern, $subject, $limit, $flags);
        if ($result === false) {
            $error = preg_last_error();
            throw new PcreErrorException($error, 'preg_split', $pattern, $subject);
        }
        return $result;
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
