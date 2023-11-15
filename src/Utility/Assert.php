<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Exception\AssertionFailedException;
use Lkrms\Exception\FilesystemErrorException;

/**
 * Throw an exception if a condition isn't met
 */
final class Assert extends Utility
{
    /**
     * Assert that a file or directory exists
     *
     * @throws FilesystemErrorException if `$filename` does not exist.
     */
    public static function fileExists(string $filename): void
    {
        if (file_exists($filename)) {
            return;
        }
        throw new FilesystemErrorException(sprintf('File not found: %s', $filename));
    }

    /**
     * Assert that a file exists
     *
     * @throws FilesystemErrorException if `$filename` is not a regular file.
     */
    public static function isFile(string $filename): void
    {
        if (is_file($filename)) {
            return;
        }
        throw new FilesystemErrorException(sprintf('Not a file: %s', $filename));
    }

    /**
     * Assert that a directory exists
     *
     * @throws FilesystemErrorException if `$filename` is not a directory.
     */
    public static function isDir(string $filename): void
    {
        if (is_dir($filename)) {
            return;
        }
        throw new FilesystemErrorException(sprintf('Not a directory: %s', $filename));
    }

    /**
     * Assert that a value is not empty
     *
     * @param mixed $value
     * @throws AssertionFailedException if `$value == false`.
     */
    public static function notEmpty($value, ?string $name = null): void
    {
        if ($value) {
            return;
        }
        self::throw('{} cannot be empty', $name);
    }

    /**
     * Assert that a value is an instance of a class or interface
     *
     * @template T of object
     *
     * @param mixed $value
     * @param class-string<T> $class
     * @throws AssertionFailedException if `$value` does not inherit `$class`.
     * @phpstan-assert T $value
     */
    public static function instanceOf($value, string $class, ?string $name = null): void
    {
        if (is_a($value, $class)) {
            return;
        }
        self::throw(sprintf('{} must be an instance of %s', $class), $name);
    }

    /**
     * Assert that a value is an array
     *
     * @param mixed $value
     * @throws AssertionFailedException if `$value` is not an array.
     * @phpstan-assert mixed[] $value
     */
    public static function isArray($value, ?string $name = null): void
    {
        if (is_array($value)) {
            return;
        }
        self::throw('{} must be an array', $name);
    }

    /**
     * Assert that a value is an integer
     *
     * @param mixed $value
     * @throws AssertionFailedException if `$value` is not an integer.
     * @phpstan-assert int $value
     */
    public static function isInt($value, ?string $name = null): void
    {
        if (is_int($value)) {
            return;
        }
        self::throw('{} must be an integer', $name);
    }

    /**
     * Assert that a value is a string
     *
     * @param mixed $value
     * @throws AssertionFailedException if `$value` is not a string.
     * @phpstan-assert string $value
     */
    public static function isString($value, ?string $name = null): void
    {
        if (is_string($value)) {
            return;
        }
        self::throw('{} must be a string', $name);
    }

    /**
     * Assert that a value is a string that matches a regular expression
     *
     * @param mixed $value
     * @throws AssertionFailedException if `$value` is not a string or does not
     * match `$pattern`.
     * @phpstan-assert string $value
     */
    public static function isMatch($value, string $pattern, ?string $name = null): void
    {
        if (is_string($value) && Pcre::match($pattern, $value)) {
            return;
        }
        self::throw(sprintf('{} must match regular expression: %s', $pattern), $name);
    }

    /**
     * Assert that PHP is running on the command line
     *
     * @throws AssertionFailedException if the value of PHP_SAPI is not `"cli"`.
     */
    public static function runningOnCli(): void
    {
        if (\PHP_SAPI === 'cli') {
            return;
        }
        // @codeCoverageIgnore
        throw new AssertionFailedException('CLI required');
    }

    /**
     * Assert that PHP's register_argc_argv directive is enabled
     *
     * @throws AssertionFailedException if `register_argc_argv` is disabled.
     */
    public static function argvIsDeclared(): void
    {
        if (ini_get('register_argc_argv')) {
            return;
        }
        // @codeCoverageIgnore
        throw new AssertionFailedException('register_argc_argv must be enabled');
    }

    private static function throw(string $message, ?string $name): void
    {
        throw new AssertionFailedException(
            str_replace(
                '{}',
                $name === null ? 'value' : $name,
                $message
            )
        );
    }
}
