<?php declare(strict_types=1);

namespace Salient\Utility;

use Salient\Utility\Exception\PcreErrorException;
use Stringable;

/**
 * PCRE function wrappers that throw an exception on failure
 *
 * @api
 */
final class Regex extends AbstractUtility
{
    /**
     * Characters with the "Default_Ignorable_Code_Point" property or in the
     * "Space_Separator" category
     *
     * @link https://util.unicode.org/UnicodeJsps/list-unicodeset.jsp?a=[[:Default_Ignorable_Code_Point=Yes:][:General_Category=Space_Separator:]-[\u0020]]
     */
    public const INVISIBLE_CHAR = '['
        . '\x{00A0}\x{00AD}'
        . '\x{034F}'
        . '\x{061C}'
        . '\x{115F}\x{1160}'
        . '\x{1680}'
        . '\x{17B4}\x{17B5}'
        . '\x{180B}-\x{180F}'
        . '\x{2000}-\x{200F}\x{202A}-\x{202F}\x{205F}-\x{206F}'
        . '\x{3000}'
        . '\x{3164}'
        . '\x{FE00}-\x{FE0F}'
        . '\x{FEFF}'
        . '\x{FFA0}'
        . '\x{FFF0}-\x{FFF8}'
        . '\x{1BCA0}-\x{1BCA3}'
        . '\x{1D173}-\x{1D17A}'
        . '\x{E0000}-\x{E0FFF}'
        . ']';

    /**
     * A boolean string
     */
    public const BOOLEAN_STRING = '(?:(?i)'
        . '(?<true>1|on|y(?:es)?|true|enabled?)|'
        . '(?<false>0|off|no?|false|disabled?)'
        . ')';

    /**
     * An integer string
     */
    public const INTEGER_STRING = '(?:[+-]?[0-9]+)';

    /**
     * An [RFC4122]-compliant version 4 UUID
     */
    public const UUID = '(?:(?i)[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})';

    /**
     * A 12-byte MongoDB ObjectId
     */
    public const MONGODB_OBJECTID = '(?:(?i)[0-9a-f]{24})';

    /**
     * A PHP identifier
     *
     * @link https://www.php.net/manual/en/language.variables.basics.php
     * @link https://www.php.net/manual/en/language.oop5.basic.php
     */
    public const PHP_IDENTIFIER = '(?:[[:alpha:]_\x80-\xff][[:alnum:]_\x80-\xff]*)';

    /**
     * A PHP type, i.e. an identifier with an optional namespace
     */
    public const PHP_TYPE = '(?:(?:\\\\?' . self::PHP_IDENTIFIER . ')+)';

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
     * @param TFlags $flags
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
     * @param TFlags $flags
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
     */
    public static function matchAll(
        string $pattern,
        string $subject,
        ?array &$matches = null,
        int $flags = 0,
        int $offset = 0
    ): int {
        // @phpstan-ignore paramOut.type
        $result = preg_match_all($pattern, $subject, $matches, $flags, $offset);
        if ($result === false) {
            throw new PcreErrorException(null, 'preg_match_all', $pattern, $subject);
        }
        return $result;
    }

    /**
     * A wrapper for preg_replace()
     *
     * @param string[]|string $pattern
     * @param string[]|string $replacement
     * @param string[]|string $subject
     * @param-out int $count
     * @return ($subject is string[] ? string[] : string)
     */
    public static function replace(
        $pattern,
        $replacement,
        $subject,
        int $limit = -1,
        ?int &$count = null
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
     * @param-out int $count
     * @return T
     */
    public static function replaceCallback(
        $pattern,
        callable $callback,
        $subject,
        int $limit = -1,
        ?int &$count = null,
        int $flags = 0
    ) {
        // @phpstan-ignore argument.type
        $result = preg_replace_callback($pattern, $callback, $subject, $limit, $count, $flags);
        if ($result === null) {
            throw new PcreErrorException(null, 'preg_replace_callback', $pattern, $subject);
        }
        // @phpstan-ignore return.type
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
        ?int &$count = null,
        int $flags = 0
    ) {
        $result = preg_replace_callback_array($pattern, $subject, $limit, $count, $flags);
        if ($result === null) {
            throw new PcreErrorException(null, 'preg_replace_callback_array', $pattern, $subject);
        }
        // @phpstan-ignore return.type
        return $result;
    }

    /**
     * A wrapper for preg_split()
     *
     * @param int-mask-of<\PREG_SPLIT_NO_EMPTY|\PREG_SPLIT_DELIM_CAPTURE|\PREG_SPLIT_OFFSET_CAPTURE> $flags
     * @return ($flags is 1|3|5|7 ? string[] : non-empty-array<string>)
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
        return $delimiter
            . str_replace($delimiter, '\\' . $delimiter, $pattern)
            . $delimiter;
    }

    /**
     * Quote a string for use in a regular expression
     *
     * @param string|null $delimiter The PCRE pattern delimiter to escape.
     * Forward slash (`'/'`) is the most commonly used delimiter.
     */
    public static function quote(
        string $str,
        ?string $delimiter = null
    ): string {
        return preg_quote($str, $delimiter);
    }

    /**
     * Quote characters for use in a character class
     *
     * @param string|null $delimiter The PCRE pattern delimiter to escape.
     * Forward slash (`'/'`) is the most commonly used delimiter.
     */
    public static function quoteCharacters(
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

    /**
     * Quote a string for use with replace()
     */
    public static function quoteReplacement(string $replacement): string
    {
        return self::replace('/[$\\\\]/', '\\\\$0', $replacement);
    }
}
