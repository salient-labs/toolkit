<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Exception\AssertionFailedException;
use Lkrms\Exception\FilesystemErrorException;

/**
 * Throw an exception if a condition isn't met
 */
final class Assert
{
    private static function throwException(string $message, ?string $name): void
    {
        throw new AssertionFailedException(
            str_replace(
                '{}',
                $name === null ? 'value' : "'$name'",
                $message
            )
        );
    }

    /**
     * Assert that a file or directory exists
     *
     * @throws FilesystemErrorException if `$filename` does not exist.
     */
    public static function fileExists(string $filename): void
    {
        if (!file_exists($filename)) {
            throw new FilesystemErrorException(
                sprintf('File not found: %s', $filename)
            );
        }
    }

    /**
     * Assert that a file exists
     *
     * @throws FilesystemErrorException if `$filename` is not a regular file.
     */
    public static function isFile(string $filename): void
    {
        if (!is_file($filename)) {
            throw new FilesystemErrorException(
                sprintf('Not a file: %s', $filename)
            );
        }
    }

    /**
     * Assert that a directory exists
     *
     * @throws FilesystemErrorException if `$filename` is not a directory.
     */
    public static function isDir(string $filename): void
    {
        if (!is_dir($filename)) {
            throw new FilesystemErrorException(
                sprintf('Not a directory: %s', $filename)
            );
        }
    }

    /**
     * @param mixed $value
     */
    public static function notEmpty($value, ?string $name = null): void
    {
        if (empty($value)) {
            self::throwException('{} cannot be empty', $name);
        }
    }

    public static function patternMatches(?string $value, string $pattern, ?string $name = null): void
    {
        if (is_null($value) || !preg_match($pattern, $value)) {
            self::throwException("{} must match pattern '$pattern'", $name);
        }
    }

    public static function sapiIsCli(): void
    {
        if (PHP_SAPI !== 'cli') {
            throw new AssertionFailedException('CLI required');
        }
    }

    public static function argvIsRegistered(): void
    {
        if (!ini_get('register_argc_argv')) {
            throw new AssertionFailedException('register_argc_argv is not enabled');
        }
    }

    public static function localeIsUtf8(): void
    {
        if (($locale = setlocale(LC_CTYPE, '0')) === false) {
            throw new AssertionFailedException('Invalid locale settings');
        }

        if (!preg_match('/\.utf-?8$/i', $locale)) {
            throw new AssertionFailedException("'$locale' is not a UTF-8 locale");
        }
    }
}
