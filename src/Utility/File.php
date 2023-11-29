<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Exception\FilesystemErrorException;
use Lkrms\Exception\IncompatibleRuntimeEnvironmentException;
use Lkrms\Exception\InvalidEnvironmentException;
use Lkrms\Facade\Sys;
use Lkrms\Iterator\RecursiveFilesystemIterator;
use Phar;

/**
 * Work with files and directories
 */
final class File
{
    /**
     * Open a file or URL
     *
     * A wrapper around {@see fopen()} that throws an exception on failure.
     *
     * @return resource
     */
    public static function open(string $filename, string $mode)
    {
        $stream = fopen($filename, $mode);
        if ($stream === false) {
            throw new FilesystemErrorException(
                sprintf('Error opening stream: %s', $filename),
            );
        }
        return $stream;
    }

    /**
     * Close an open stream
     *
     * A wrapper around {@see fclose()} that throws an exception on failure.
     *
     * @param resource $stream
     */
    public static function close($stream, ?string $uri = null): void
    {
        $uri = self::maybeGetStreamUri($stream, $uri);
        $result = fclose($stream);
        if ($result === false) {
            throw new FilesystemErrorException(
                sprintf('Error closing file: %s', $uri),
            );
        }
    }

    /**
     * Write to an open stream
     *
     * A wrapper around {@see fwrite()} that throws an exception on failure and
     * when fewer bytes are written than expected.
     *
     * @param resource $stream
     */
    public static function write($stream, string $data, ?int $length = null, ?string $uri = null): int
    {
        $result = fwrite($stream, $data, $length);
        if ($result === false) {
            throw new FilesystemErrorException(
                sprintf(
                    'Error writing to stream: %s',
                    self::maybeGetStreamUri($stream, $uri),
                ),
            );
        }
        $length = $length === null ? strlen($data) : min($length, strlen($data));
        if ($result !== $length) {
            throw new FilesystemErrorException(
                sprintf(
                    'Error writing to stream: %d of %d %s written to %s',
                    $result,
                    $length,
                    Convert::plural($length, 'byte'),
                    self::maybeGetStreamUri($stream, $uri),
                ),
            );
        }
        return $result;
    }

    /**
     * Set the file position indicator for a stream
     *
     * A wrapper around {@see fseek()} that throws an exception on failure.
     *
     * @param resource $stream
     * @param \SEEK_SET|\SEEK_CUR|\SEEK_END $whence
     */
    public static function seek($stream, int $offset, int $whence = SEEK_SET, ?string $uri = null): void
    {
        $result = fseek($stream, $offset, $whence);
        if ($result === -1) {
            throw new FilesystemErrorException(
                sprintf(
                    'Error setting file position indicator for stream: %s',
                    self::maybeGetStreamUri($stream, $uri),
                ),
            );
        }
    }

    /**
     * Get the file position indicator for a stream
     *
     * A wrapper around {@see ftell()} that throws an exception on failure.
     *
     * @param resource $stream
     */
    public static function tell($stream, ?string $uri = null): int
    {
        $result = ftell($stream);
        if ($result === false) {
            throw new FilesystemErrorException(
                sprintf(
                    'Error getting file position indicator for stream: %s',
                    self::maybeGetStreamUri($stream, $uri),
                ),
            );
        }
        return $result;
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
     * @see Inspect::getEol()
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
     *      by {@see Convert::resolvePath()}
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
        if (Test::isPharUrl($filename) &&
                extension_loaded('Phar') &&
                Phar::running()) {
            // @codeCoverageIgnoreStart
            $filename = Convert::resolvePath($filename);

            return file_exists($filename) ? $filename : false;
            // @codeCoverageIgnoreEnd
        }

        return realpath($filename);
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
            $basePath = File::realpath($parentDir);
        }
        Assert::fileExists($filename);
        $path = File::realpath($filename);
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
     * @param resource $stream
     */
    private static function maybeGetStreamUri($stream, ?string $uri): string
    {
        if ($uri !== null) {
            return $uri;
        }
        $uri = self::getStreamUri($stream);
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
            $type = get_resource_type($target);
            if ($type !== 'stream') {
                throw new FilesystemErrorException(
                    sprintf('Invalid resource type: %s', $type),
                );
            }
            $handle = $target;
            $targetName = 'stream';
        } elseif (is_string($target)) {
            $handle = self::open($target, 'wb');
            $targetName = $target;
        } else {
            $target = 'php://temp';
            $handle = self::open($target, 'w+b');
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
     */
    private static function fputcsv(
        $stream,
        array $fields,
        string $separator = ',',
        string $enclosure = '"',
        string $eol = "\n",
        ?string $uri = null
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
