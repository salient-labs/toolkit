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
 * Work with streams, files and directories
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
     * @throws FilesystemErrorException on failure.
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
     * True if a stream is seekable
     *
     * @param resource $stream
     */
    public static function isSeekable($stream): bool
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            return false;
        }
        // @phpstan-ignore-next-line
        return stream_get_meta_data($stream)['seekable'] ?? false;
    }

    /**
     * Open a pipe to a process
     *
     * @see popen()
     * @return resource
     * @throws FilesystemErrorException on failure.
     */
    public static function openPipe(string $command, string $mode)
    {
        $pipe = @popen($command, $mode);
        return self::throwOnFailure($pipe, 'Error opening pipe to process: %s', $command);
    }

    /**
     * Close a pipe to a process and return its exit status
     *
     * @see pclose()
     * @param resource $pipe
     * @throws FilesystemErrorException on failure.
     */
    public static function closePipe($pipe, ?string $command = null): int
    {
        $result = @pclose($pipe);
        return self::throwOnFailure($result, 'Error closing pipe to process: %s', $command, null, -1);
    }

    /**
     * Get the entire contents of a file or the remaining contents of an open
     * stream
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
     * Write data to a file
     *
     * @see file_put_contents()
     * @param resource|array<int|float|string|bool|Stringable|null>|string $data
     * @param int-mask-of<\FILE_USE_INCLUDE_PATH|\FILE_APPEND|\LOCK_EX> $flags
     */
    public static function putContents(string $filename, $data, int $flags = 0): int
    {
        $result = @file_put_contents($filename, $data, $flags);
        return self::throwOnFailure($result, 'Error writing file: %s', $filename);
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
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     * @return string|null `null` if there are no recognised line breaks in the
     * file.
     *
     * @see Get::eol()
     * @see Str::setEol()
     */
    public static function getEol($resource, $uri = null): ?string
    {
        $close = false;
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            $handle = $resource;
        } elseif (Test::isStringable($resource)) {
            $uri = (string) $resource;
            $handle = self::open($uri, 'r');
            $close = true;
        } else {
            throw new InvalidArgumentTypeException(1, 'resource', 'Stringable|string|resource', $resource);
        }

        $line = fgets($handle);

        if ($close) {
            self::close($handle, $uri);
        }

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
        return Str::lower(substr($path, 0, 7)) === 'phar://';
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
        $tmp = self::getTempDir();
        $program = Sys::getProgramBasename();
        do {
            $dir = sprintf('%s/%s%s.tmp', $tmp, $program, Compute::randomText(8));
        } while (!@mkdir($dir, 0700));

        return $dir;
    }

    /**
     * Resolve symbolic links and relative references in a path or Phar URI
     *
     * @throws FilesystemErrorException if `$path` does not exist.
     */
    public static function realpath(string $path): string
    {
        if (self::isPharUri($path) && file_exists($path)) {
            return self::resolve($path, true);
        }

        $_path = $path;
        $path = realpath($path);
        if ($path === false) {
            throw new FilesystemErrorException(sprintf('File not found: %s', $_path));
        }
        return $path;
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
     * Returns `$fallback` if `$filename` does not belong to `$parentDir`.
     *
     * @throws FilesystemErrorException if `$filename` or `$parentDir` do not
     * exist.
     */
    public static function relativeToParent(
        string $filename,
        string $parentDir,
        ?string $fallback = null
    ): ?string {
        $path = self::realpath($filename);
        $basePath = self::realpath($parentDir);
        if (strpos($path, $basePath) === 0) {
            return substr($path, strlen($basePath) + 1);
        }
        return $fallback;
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
            return '<stream>';
        }
        return $uri;
    }

    /**
     * Write CSV-formatted data to a file or stream
     *
     * For maximum interoperability with Excel across all platforms, data is
     * written in UTF-16LE by default.
     *
     * @template TValue
     *
     * @param Stringable|string|resource $resource
     * @param iterable<TValue> $data
     * @param bool $headerRow If `true`, write the first record's array keys
     * before the first row.
     * @param int|float|string|bool|null $nullValue Optionally replace `null`
     * values before writing data.
     * @param callable(TValue): mixed[] $callback Applied to each record before
     * it is written.
     * @param int|null $count Receives the number of records written.
     * @param bool $utf16le If `true` (the default), encode output in UTF-16LE.
     * @param bool $bom If `true` (the default), add a BOM (byte order mark) to
     * the output.
     * @param Stringable|string|null $uri
     */
    public static function writeCsv(
        $resource,
        iterable $data,
        bool $headerRow = true,
        $nullValue = null,
        ?callable $callback = null,
        ?int &$count = null,
        string $eol = "\r\n",
        bool $utf16le = true,
        bool $bom = true,
        $uri = null
    ): void {
        $close = false;
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            $handle = $resource;
        } elseif (Test::isStringable($resource)) {
            $uri = (string) $resource;
            $handle = self::open($uri, 'wb');
            $close = true;
        } else {
            throw new InvalidArgumentTypeException(1, 'resource', 'Stringable|string|resource', $resource);
        }

        if ($utf16le) {
            if (!extension_loaded('iconv')) {
                throw new IncompatibleRuntimeEnvironmentException(
                    "'iconv' extension required for UTF-16LE encoding"
                );
            }
            $filter = @stream_filter_append($handle, 'convert.iconv.UTF-8.UTF-16LE', \STREAM_FILTER_WRITE);
            self::throwOnFailure($filter, 'Error applying UTF-16LE filter to stream: %s', $uri, $handle);
        }

        if ($bom) {
            self::write($handle, '﻿', null, $uri);
        }

        $count = 0;
        foreach ($data as $row) {
            if ($callback) {
                $row = $callback($row);
            }

            $row = Arr::toScalars($row, $nullValue);

            if (!$count && $headerRow) {
                self::fputcsv($handle, array_keys($row), ',', '"', $eol, $uri);
            }

            self::fputcsv($handle, $row, ',', '"', $eol, $uri);
            $count++;
        }

        if ($close) {
            self::close($handle, $uri);
        } elseif ($utf16le) {
            $result = @stream_filter_remove($filter);
            self::throwOnFailure($result, 'Error removing UTF-16LE filter from stream: %s', $uri, $handle);
        }
    }

    /**
     * Write a line of comma-separated values to an open stream
     *
     * A shim for {@see fputcsv()} with `$eol` (added in PHP 8.1) and without
     * `$escape` (which should be removed).
     *
     * @param resource $stream
     * @param mixed[] $fields
     * @param Stringable|string|null $uri
     */
    public static function fputcsv(
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
        $path = Sys::getProgramName();
        $program = basename($path);
        $path = self::realpath($path);
        $hash = Compute::hash($path);
        $user = Sys::getUserId();

        if ($dir === null) {
            $dir = self::getTempDir();
        } else {
            $dir = Str::coalesce(rtrim($dir, '/\\'), $dir, '.');
        }

        return sprintf('%s/%s-%s-%s%s', $dir, $program, $hash, $user, $suffix);
    }

    private static function getTempDir(): string
    {
        $tempDir = sys_get_temp_dir();
        $tmp = realpath($tempDir);
        if ($tmp === false || !is_dir($tmp) || !is_writable($tmp)) {
            throw new FilesystemErrorException(
                sprintf('Not a writable directory: %s', $tempDir),
            );
        }
        return $tmp;
    }
}
