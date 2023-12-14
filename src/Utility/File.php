<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Exception\FilesystemErrorException;
use Lkrms\Exception\IncompatibleRuntimeEnvironmentException;
use Lkrms\Exception\InvalidArgumentException;
use Lkrms\Exception\InvalidArgumentTypeException;
use Lkrms\Exception\InvalidEnvironmentException;
use Lkrms\Iterator\RecursiveFilesystemIterator;
use Phar;
use Stringable;

/**
 * Work with files and directories
 */
final class File extends Utility
{
    private const ABSOLUTE_PATH = <<<'REGEX'
        /^(?:\/|\\\\|[a-z]:[\/\\]|[a-z][-a-z0-9+.]+:)/i
        REGEX;

    /**
     * Open a file or URL
     *
     * @see fopen()
     * @return resource
     * @throws FilesystemErrorException on failure.
     */
    public static function open(string $filename, string $mode)
    {
        $stream = @fopen($filename, $mode);
        return self::throwOnFailure($stream, 'Error opening stream: %s', $filename);
    }

    /**
     * Close an open stream
     *
     * @see fclose()
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function close($stream, $uri = null): void
    {
        $uri = self::getFriendlyStreamUri($uri, $stream);
        $result = @fclose($stream);
        self::throwOnFailure($result, 'Error closing stream: %s', $uri);
    }

    /**
     * Read from an open stream
     *
     * @see fread()
     * @param resource $stream
     * @param Stringable|string|null $uri
     * @throws FilesystemErrorException on failure.
     */
    public static function read($stream, int $length, $uri = null): string
    {
        $result = @fread($stream, $length);
        return self::throwOnFailure($result, 'Error reading from stream: %s', $uri, $stream);
    }

    /**
     * Write to an open stream
     *
     * @see fwrite()
     * @param resource $stream
     * @param Stringable|string|null $uri
     * @throws FilesystemErrorException on failure and when fewer bytes are
     * written than expected.
     */
    public static function write($stream, string $data, ?int $length = null, $uri = null): int
    {
        // $length can't be null in PHP 7.4
        if ($length === null) {
            $length = strlen($data);
            $expected = $length;
        } else {
            $expected = min($length, strlen($data));
        }
        $result = @fwrite($stream, $data, $length);
        self::throwOnFailure($result, 'Error writing to stream: %s', $uri, $stream);
        if ($result !== $expected) {
            throw new FilesystemErrorException(
                sprintf(
                    'Error writing to stream: %d of %d %s written to %s',
                    $result,
                    $length,
                    Convert::plural($length, 'byte'),
                    self::getFriendlyStreamUri($uri, $stream),
                ),
            );
        }
        return $result;
    }

    /**
     * Set the file position indicator for a stream
     *
     * @see fseek()
     * @param resource $stream
     * @param \SEEK_SET|\SEEK_CUR|\SEEK_END $whence
     * @param Stringable|string|null $uri
     * @throws FilesystemErrorException on failure.
     */
    public static function seek($stream, int $offset, int $whence = \SEEK_SET, $uri = null): void
    {
        $result = @fseek($stream, $offset, $whence);
        self::throwOnFailure($result, 'Error setting file position indicator for stream: %s', $uri, $stream, -1);
    }

    /**
     * Get the file position indicator for a stream
     *
     * @see ftell()
     * @param resource $stream
     * @param Stringable|string|null $uri
     * @throws FilesystemErrorException on failure.
     */
    public static function tell($stream, $uri = null): int
    {
        $result = @ftell($stream);
        return self::throwOnFailure($result, 'Error getting file position indicator for stream: %s', $uri, $stream);
    }

    /**
     * Get the file status of a stream
     *
     * @see fstat()
     * @param resource $stream
     * @param Stringable|string|null $uri
     * @return int[]
     * @throws FilesystemErrorException on failure.
     */
    public static function stat($stream, $uri = null): array
    {
        $result = @fstat($stream);
        return self::throwOnFailure($result, 'Error getting file status of stream: %s', $uri, $stream);
    }

    /**
     * Get an entire file or the remaining contents of a stream
     *
     * @see file_get_contents()
     * @see stream_get_contents()
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     * @throws FilesystemErrorException on failure.
     */
    public static function getContents($resource, $uri = null): string
    {
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            $result = @stream_get_contents($resource);
            return self::throwOnFailure($result, 'Error reading stream: %s', $uri, $resource);
        }

        if (!Test::isStringable($resource)) {
            throw new InvalidArgumentTypeException(1, 'resource', 'Stringable|string|resource', $resource);
        }

        $resource = (string) $resource;
        $result = @file_get_contents($resource);
        return self::throwOnFailure($result, 'Error reading file: %s', $resource);
    }

    /**
     * Iterate over files in one or more directories
     *
     * Syntactic sugar for `new RecursiveFilesystemIterator()`.
     *
     * @see RecursiveFilesystemIterator
     */
    public static function find(): RecursiveFilesystemIterator
    {
        return new RecursiveFilesystemIterator();
    }

    /**
     * Get the end-of-line sequence used in a file
     *
     * Recognised line endings are LF (`"\n"`), CRLF (`"\r\n"`) and CR (`"\r"`).
     *
     * @return string|null `null` if there are no recognised line breaks in the
     * file.
     *
     * @see Get::eol()
     * @see Str::setEol()
     */
    public static function getEol(string $filename): ?string
    {
        $handle = self::open($filename, 'r');
        $line = fgets($handle);
        self::close($handle, $filename);

        if ($line === false) {
            return null;
        }

        foreach (["\r\n", "\n", "\r"] as $eol) {
            if (substr($line, -strlen($eol)) === $eol) {
                return $eol;
            }
        }

        if (strpos($line, "\r") !== false) {
            return "\r";
        }

        return null;
    }

    /**
     * True if two paths refer to the same filesystem entry
     */
    public static function is(string $filename1, string $filename2): bool
    {
        if (!file_exists($filename1) || !file_exists($filename2)) {
            return false;
        }
        $inode = fileinode($filename1);
        return $inode !== false &&
            fileinode($filename2) === $inode;
    }

    /**
     * True if a file appears to contain PHP code
     *
     * Returns `true` if `$filename` has a PHP open tag (`<?php`) at the start
     * of the first line that is not a shebang (`#!`).
     */
    public static function isPhp(string $filename): bool
    {
        $handle = self::open($filename, 'r');
        $line = fgets($handle);
        if ($line !== false && substr($line, 0, 2) === '#!') {
            $line = fgets($handle);
        }
        self::close($handle, $filename);

        if ($line === false) {
            return false;
        }

        return (bool) Pcre::match('/^<\?(php\s|(?!php|xml\s))/', $line);
    }

    /**
     * True if a path is absolute
     *
     * Returns `true` if `$path` starts with `/`, `\\`, `<letter>:\`,
     * `<letter>:/` or a URI scheme with two or more characters.
     */
    public static function isAbsolute(string $path): bool
    {
        return (bool) Pcre::match(self::ABSOLUTE_PATH, $path);
    }

    /**
     * True if a path is a "phar://" URI
     */
    public static function isPharUri(string $path): bool
    {
        return strtolower(substr($path, 0, 7)) === 'phar://';
    }

    /**
     * Create a file if it doesn't exist
     *
     * @param int $permissions Used after creating `$filename` if it doesn't
     * exist.
     * @param int $dirPermissions Used if one or more directories above
     * `$filename` don't exist.
     */
    public static function create(
        string $filename,
        int $permissions = 0777,
        int $dirPermissions = 0777
    ): void {
        if (is_file($filename)) {
            return;
        }

        self::createDir(dirname($filename), $dirPermissions);

        $result = touch($filename) && chmod($filename, $permissions);
        if (!$result) {
            throw new FilesystemErrorException(
                sprintf('Error creating file: %s', $filename),
            );
        }
    }

    /**
     * Create a directory if it doesn't exist
     *
     * @param int $permissions Used if `$directory` doesn't exist.
     */
    public static function createDir(
        string $directory,
        int $permissions = 0777
    ): void {
        if (is_dir($directory)) {
            return;
        }

        $result = mkdir($directory, $permissions, true);
        if (!$result) {
            throw new FilesystemErrorException(
                sprintf('Error creating directory: %s', $directory),
            );
        }
    }

    /**
     * Delete a file if it exists
     */
    public static function delete(string $filename): void
    {
        if (!file_exists($filename)) {
            return;
        }

        if (!is_file($filename)) {
            throw new FilesystemErrorException(
                sprintf('Not a file: %s', $filename),
            );
        }

        $result = unlink($filename);
        if (!$result) {
            throw new FilesystemErrorException(
                sprintf('Error deleting file: %s', $filename),
            );
        }
    }

    /**
     * Delete a directory if it exists
     */
    public static function deleteDir(
        string $directory,
        bool $recursive = false
    ): void {
        if (!file_exists($directory)) {
            return;
        }

        if (!is_dir($directory)) {
            throw new FilesystemErrorException(
                sprintf('Not a directory: %s', $directory),
            );
        }

        if ($recursive) {
            self::pruneDir($directory);
        }

        $result = rmdir($directory);
        if (!$result) {
            throw new FilesystemErrorException(
                sprintf('Error deleting directory: %s', $directory),
            );
        }
    }

    /**
     * Recursively delete the contents of a directory without deleting the
     * directory itself
     */
    public static function pruneDir(string $directory): void
    {
        $files = (new RecursiveFilesystemIterator())
            ->in($directory)
            ->dirs()
            ->dirsLast();

        foreach ($files as $file) {
            $result =
                $file->isDir()
                    ? rmdir((string) $file)
                    : unlink((string) $file);

            if (!$result) {
                throw new FilesystemErrorException(
                    sprintf('Error pruning directory: %s', $directory),
                );
            }
        }
    }

    /**
     * Create a temporary directory
     */
    public static function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir();
        $tmp = realpath($tempDir);
        if ($tmp === false || !is_dir($tmp) || !is_writable($tmp)) {
            throw new FilesystemErrorException(
                sprintf('Not a writable directory: %s', $tempDir),
            );
        }

        $program = Sys::getProgramBasename();
        do {
            $dir = sprintf('%s/%s%s.tmp', $tmp, $program, Compute::randomText(8));
        } while (!@mkdir($dir, 0700));

        return $dir;
    }

    /**
     * A Phar-friendly, file descriptor-aware realpath()
     *
     * 1. If `$filename` is a file descriptor in `/dev/fd` or `/proc`,
     *    `php://fd/<DESCRIPTOR>` is returned.
     *
     * 2. If a Phar archive is running and `$filename` is a `phar://` URL:
     *    - relative path segments in `$filename` (e.g. `/../..`) are resolved
     *      by {@see File::resolve()}
     *    - if the file or directory exists, the resolved pathname is returned
     *    - if `$filename` doesn't exist, `false` is returned
     *
     * 3. The return value of `realpath($filename)` is returned.
     *
     * @return string|false
     */
    public static function realpath(string $filename)
    {
        if (Pcre::match(
            '#^/(?:dev|proc/(?:self|[0-9]+))/fd/([0-9]+)$#',
            $filename,
            $matches
        )) {
            return 'php://fd/' . $matches[1];
        }
        if (self::isPharUri($filename) &&
                extension_loaded('Phar') &&
                Phar::running()) {
            // @codeCoverageIgnoreStart
            $filename = self::resolve($filename);

            return file_exists($filename) ? $filename : false;
            // @codeCoverageIgnoreEnd
        }

        return realpath($filename);
    }

    /**
     * Resolve "/./" and "/../" segments in a path
     *
     * Relative directory segments are removed without accessing the filesystem,
     * so `$path` need not exist.
     *
     * If `$withEmptySegments` is `true`, a `"/../"` segment after two or more
     * consecutive directory separators is resolved by removing one of the
     * separators. If `false` (the default), it is resolved by treating
     * consecutive separators as one separator.
     *
     * Example:
     *
     * ```php
     * <?php
     * echo File::resolve('/dir/subdir//../') . PHP_EOL;
     * echo File::resolve('/dir/subdir//../', true) . PHP_EOL;
     * ```
     *
     * Output:
     *
     * ```
     * /dir/
     * /dir/subdir/
     * ```
     */
    public static function resolve(string $path, bool $withEmptySegments = false): string
    {
        $path = str_replace('\\', '/', $path);

        // Remove "/./" segments
        $path = Pcre::replace('@(?<=/|^)\.(?:/|$)@', '', $path);

        // Remove "/../" segments
        $regex = $withEmptySegments ? '/' : '/+';
        $regex = "@(?:^|(?<=^/)|(?<=/|^(?!/))(?!\.\.(?:/|\$))[^/]*{$regex})\.\.(?:/|\$)@";
        do {
            $path = Pcre::replace($regex, '', $path, -1, $count);
        } while ($count);

        return $path;
    }

    /**
     * Get a path relative to a parent directory
     *
     * If `$parentDir` is `null`, the path of the root package is used.
     *
     * @throws FilesystemErrorException if `$filename` or `$parentDir` do not
     * exist or if `$filename` does not belong to `$parentDir`.
     */
    public static function relativeToParent(
        string $filename,
        ?string $parentDir = null
    ): string {
        if ($parentDir === null) {
            $basePath = Package::path();
        } else {
            Assert::fileExists($parentDir);
            $basePath = self::realpath($parentDir);
        }
        Assert::fileExists($filename);
        $path = self::realpath($filename);
        if (strpos($path, $basePath) === 0) {
            return substr($path, strlen($basePath) + 1);
        }
        throw new FilesystemErrorException(
            sprintf("'%s' does not belong to '%s'", $filename, $parentDir)
        );
    }

    /**
     * Get the URI associated with a stream
     *
     * @param resource $stream
     * @return string|null `null` if `$stream` is closed or does not have a URI.
     */
    public static function getStreamUri($stream): ?string
    {
        if (is_resource($stream) && get_resource_type($stream) === 'stream') {
            // @phpstan-ignore-next-line
            return stream_get_meta_data($stream)['uri'] ?? null;
        }
        return null;
    }

    /**
     * @template TSuccess
     * @template TFailure of false|-1
     *
     * @param TSuccess|TFailure $result
     * @param Stringable|string|null $uri
     * @param resource|null $stream
     * @param TFailure $failure
     * @return ($result is TFailure ? never : TSuccess)
     */
    private static function throwOnFailure($result, string $message, $uri, $stream = null, $failure = false)
    {
        if ($result === $failure) {
            $error = error_get_last();
            if ($error) {
                throw new FilesystemErrorException($error['message']);
            }
            throw new FilesystemErrorException(
                sprintf($message, self::getFriendlyStreamUri($uri, $stream))
            );
        }
        return $result;
    }

    /**
     * @param Stringable|string|null $uri
     * @param resource|null $stream
     */
    private static function getFriendlyStreamUri($uri, $stream): string
    {
        if ($uri !== null) {
            return (string) $uri;
        }
        if ($stream !== null) {
            $uri = self::getStreamUri($stream);
        }
        if ($uri === null) {
            return '<no URI>';
        }
        return $uri;
    }

    /**
     * Write data to a CSV file or stream
     *
     * For maximum interoperability with Excel across all platforms, data is
     * written in UTF-16LE by default.
     *
     * @param iterable<mixed[]> $data Data to write.
     * @param string|resource|null $target Either a filename, a stream opened by
     * `fopen()`, `fsockopen()` or similar, or `null`. If `null`, data is
     * returned as a string.
     * @param bool $headerRow If `true`, write the first record's array keys
     * before the first row.
     * @param int|string|null $nullValue Optionally replace `null` values before
     * writing data.
     * @param callable(mixed): mixed[] $callback Applied to each record before
     * it is written.
     * @param int|null $count Receives the number of records written.
     * @param bool $utf16le If `true` (the default), encode output in UTF-16LE.
     * @param bool $bom If `true` (the default), add a BOM (byte order mark) to
     * the output.
     * @return string|true
     */
    public static function writeCsv(
        iterable $data,
        $target = null,
        bool $headerRow = true,
        $nullValue = null,
        ?callable $callback = null,
        ?int &$count = null,
        string $eol = "\r\n",
        bool $utf16le = true,
        bool $bom = true
    ) {
        if (is_resource($target)) {
            self::assertResourceIsStream($target);
            $handle = $target;
            $targetName = 'stream';
        } elseif (is_string($target)) {
            $handle = self::open($target, 'wb');
            $targetName = $target;
        } else {
            $target = 'php://temp';
            $handle = self::open($target, 'r+b');
            $targetName = $target;
            $target = null;
        }

        if ($utf16le) {
            if (!extension_loaded('iconv')) {
                throw new IncompatibleRuntimeEnvironmentException(
                    "'iconv' extension required for UTF-16LE encoding",
                );
            }
            $filter = 'convert.iconv.UTF-8.UTF-16LE';
            $result = stream_filter_append(
                $handle,
                $filter,
                \STREAM_FILTER_WRITE,
            );
            if ($result === false) {
                throw new FilesystemErrorException(
                    sprintf('Error applying filter to stream: %s', $filter),
                );
            }
        }

        if ($bom) {
            self::write($handle, '﻿', null, $targetName);
        }

        $count = 0;
        foreach ($data as $row) {
            if ($callback) {
                $row = $callback($row);
            }

            foreach ($row as &$value) {
                if ($value === null) {
                    $value = $nullValue;
                } elseif (!is_scalar($value)) {
                    $value = json_encode($value);
                }
            }

            if (!$count && $headerRow) {
                self::fputcsv($handle, array_keys($row), ',', '"', $eol, $targetName);
            }

            self::fputcsv($handle, $row, ',', '"', $eol);
            $count++;
        }

        if ($target === null) {
            self::seek($handle, 0);
            $csv = stream_get_contents($handle);
            self::close($handle, $targetName);

            return $csv;
        }
        if (is_string($target)) {
            self::close($handle, $targetName);
        }

        return true;
    }

    /**
     * A polyfill for PHP 8.1's fputcsv, minus $escape
     *
     * @param resource $stream
     * @param mixed[] $fields
     * @param Stringable|string|null $uri
     */
    private static function fputcsv(
        $stream,
        array $fields,
        string $separator = ',',
        string $enclosure = '"',
        string $eol = "\n",
        $uri = null
    ): int {
        $special = $separator . $enclosure . "\n\r\t ";
        foreach ($fields as &$field) {
            if (strpbrk((string) $field, $special) !== false) {
                $field = $enclosure
                    . str_replace($enclosure, $enclosure . $enclosure, $field)
                    . $enclosure;
            }
        }

        return self::write(
            $stream,
            implode($separator, $fields) . $eol,
            null,
            $uri,
        );
    }

    /**
     * @param resource $resource
     */
    private static function assertResourceIsStream($resource): void
    {
        $type = get_resource_type($resource);
        if ($type !== 'stream') {
            throw new InvalidArgumentException(
                sprintf('Invalid resource type: %s', $type)
            );
        }
    }

    /**
     * Generate a filename unique to the current user and the path of the
     * running script
     *
     * If `$dir` is not given, a filename in `sys_get_temp_dir()` is returned.
     *
     * No changes are made to the filesystem.
     */
    public static function getStablePath(
        string $suffix = '',
        ?string $dir = null
    ): string {
        $program = Sys::getProgramName();
        $path = self::realpath($program);
        if ($path === false) {
            throw new FilesystemErrorException(
                'Unable to resolve filename used to run the script',
            );
        }
        $program = basename($program);
        $hash = Compute::hash($path);

        if (function_exists('posix_geteuid')) {
            $user = posix_geteuid();
        } else {
            $user = Env::getNullable('USERNAME', null);
            if ($user === null) {
                $user = Env::getNullable('USER', null);
                if ($user === null) {
                    throw new InvalidEnvironmentException(
                        'Unable to identify user'
                    );
                }
            }
        }

        if ($dir === null) {
            $tempDir = sys_get_temp_dir();
            $tmp = realpath($tempDir);
            if ($tmp === false || !is_dir($tmp) || !is_writable($tmp)) {
                throw new FilesystemErrorException(
                    sprintf('Not a writable directory: %s', $tempDir)
                );
            }
            $dir = $tmp;
        } else {
            $trimmed = rtrim($dir, '/\\');
            $dir = $trimmed === '' ? $dir : $trimmed;
        }

        return sprintf(
            '%s/%s-%s-%s%s',
            $dir === ''
                ? '.'
                : $dir,
            $program,
            $hash,
            $user,
            $suffix,
        );
    }
}
