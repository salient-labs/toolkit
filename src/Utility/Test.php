<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Stringable;

/**
 * Perform true/false tests on values
 */
final class Test extends Utility
{
    /**
     * True if a value is a boolean or boolean string
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
    public static function isBoolValue($value): bool
    {
        return is_bool($value) ||
            (is_string($value) && Pcre::match('/^' . Regex::BOOLEAN_STRING . '$/', $value));
    }

    /**
     * True if a value is an integer or integer string
     *
     * @param mixed $value
     */
    public static function isIntValue($value): bool
    {
        return is_int($value) ||
            (is_string($value) && Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value));
    }

    /**
     * True if a value is a float or float string
     *
     * @param mixed $value
     */
    public static function isFloatValue($value): bool
    {
        return is_float($value) ||
            (is_string($value) && is_numeric($value) && !self::isIntValue($value));
    }

    /**
     * True if a value is a string or Stringable
     *
     * @param mixed $value
     * @phpstan-assert-if-true Stringable|string $value
     */
    public static function isStringable($value): bool
    {
        return is_string($value) ||
            $value instanceof Stringable ||
            (is_object($value) && method_exists($value, '__toString'));
    }

    /**
     * True if a value is a number within a range
     *
     * @template T of int|float
     *
     * @param T $value
     * @param T $min
     * @param T $max
     */
    public static function isBetween($value, $min, $max): bool
    {
        return $value >= $min && $value <= $max;
    }

    /**
     * @deprecated Use {@see File::isPharUri()} instead
     * @codeCoverageIgnore
     */
    public static function isPharUrl(string $path): bool
    {
        return File::isPharUri($path);
    }

    /**
     * @deprecated Use {@see File::isAbsolute()} instead
     * @codeCoverageIgnore
     */
    public static function isAbsolutePath(string $path): bool
    {
        return File::isAbsolute($path);
    }

    /**
     * @deprecated Use {@see File::is()} instead
     * @codeCoverageIgnore
     */
    public static function areSameFile(string $path1, string $path2): bool
    {
        return File::is($path1, $path2);
    }

    /**
     * True if a directory exists and is writable, or doesn't exist but
     * can be created
     */
    public static function firstExistingDirectoryIsWritable(string $dir): bool
    {
        while (!file_exists($dir)) {
            $next = dirname($dir);
            if ($next === $dir) {
                break;
            }
            $dir = $next;
        }

        return is_dir($dir) && is_writable($dir);
    }

    /**
     * True if a value is a PHP reserved word
     *
     * @link https://www.php.net/manual/en/reserved.php
     */
    public static function isPhpReservedWord(string $value): bool
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
        ]);
    }
}
