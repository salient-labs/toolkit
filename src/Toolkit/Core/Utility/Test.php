<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Contract\Core\Regex;
use Salient\Core\AbstractUtility;
use Stringable;

/**
 * Perform true/false tests on values
 *
 * @api
 */
final class Test extends AbstractUtility
{
    /**
     * Check if a value is a boolean or boolean string
     *
     * The following are regarded as boolean strings (case-insensitive):
     *
     * - `"1"`, `"0"`
     * - `"on"`, `"off"`
     * - `"true"`, `"false"`
     * - `"y"`, `"n"`
     * - `"yes"`, `"no"`
     * - `"enable"`, `"disable"`
     * - `"enabled"`, `"disabled"`
     *
     * @param mixed $value
     */
    public static function isBoolean($value): bool
    {
        return is_bool($value) || (
            is_string($value)
            && Pcre::match('/^' . Regex::BOOLEAN_STRING . '$/', $value)
        );
    }

    /**
     * Check if a value is an integer or integer string
     *
     * @param mixed $value
     */
    public static function isInteger($value): bool
    {
        return is_int($value) || (
            is_string($value)
            && Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value)
        );
    }

    /**
     * Check if a value is a float or float string
     *
     * Returns `false` if `$value` is an integer string.
     *
     * @param mixed $value
     */
    public static function isFloat($value): bool
    {
        return is_float($value) || (
            is_string($value)
            && is_numeric(trim($value))
            && !Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value)
        );
    }

    /**
     * Check if a value is an integer or would be cast to an integer if used as
     * an array key
     *
     * @param mixed $value
     */
    public static function isNumericKey($value): bool
    {
        return is_int($value)
            || is_float($value)
            || is_bool($value)
            || (is_string($value) && Pcre::match('/^(-?[1-9][0-9]*|0)$/D', $value));
    }

    /**
     * Check if a value is a valid date string
     *
     * @param mixed $value
     */
    public static function isDateString($value): bool
    {
        return is_string($value) && strtotime($value) !== false;
    }

    /**
     * Check if a value is a string where every character has a codepoint
     * between 0 and 127
     *
     * @param mixed $value
     */
    public static function isAsciiString($value): bool
    {
        return is_string($value) && !Pcre::match('/[^\x00-\x7f]/', $value);
    }

    /**
     * Check if a value is a string or Stringable
     *
     * @param mixed $value
     * @phpstan-assert-if-true Stringable|string $value
     */
    public static function isStringable($value): bool
    {
        return is_string($value)
            || $value instanceof Stringable
            || (is_object($value) && method_exists($value, '__toString'));
    }

    /**
     * Check if a value is a number within a range
     *
     * @param int|float $value
     * @param int|float $min
     * @param int|float $max
     */
    public static function isBetween($value, $min, $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * Check if a value is a reserved PHP type
     *
     * @link https://www.php.net/manual/en/reserved.php
     */
    public static function isBuiltinType(string $value): bool
    {
        return in_array(Str::lower($value), [
            'array',
            'bool',
            'callable',
            'enum',
            'false',
            'float',
            'int',
            'iterable',
            'mixed',
            'never',
            'null',
            'numeric',
            'object',
            'parent',
            'resource',
            'self',
            'static',
            'string',
            'true',
            'void',
        ], true);
    }

    /**
     * Check if a value is a valid PHP class name
     *
     * @param mixed $value
     * @phpstan-assert-if-true class-string $value
     */
    public static function isFqcn($value): bool
    {
        return is_string($value)
            && Pcre::match('/^' . Regex::PHP_TYPE . '$/D', $value);
    }
}
