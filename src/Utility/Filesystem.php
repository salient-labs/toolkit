<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Exception\FilesystemErrorException;
use Lkrms\Exception\InvalidEnvironmentException;
use Lkrms\Exception\InvalidRuntimeConfigurationException;
use Lkrms\Facade\Sys;
use Lkrms\Iterator\RecursiveFilesystemIterator;
use Phar;

/**
 * Work with files and directories
 */
final class Filesystem
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
        $handle = fopen($filename, $mode);
        if ($handle === false) {
            throw new FilesystemErrorException(
                sprintf('Error opening file: %s', $filename),
            );
        }

        return $handle;
    }

    /**
     * Close an open file or URL
     *
     * A wrapper around {@see fclose()} that throws an exception on failure.
     *
     * @param resource $handle
     */
    public static function close($handle, string $filename): void
    {
        $result = fclose($handle);
        if ($result === false) {
            throw new FilesystemErrorException(
                sprintf('Error closing file: %s', $filename),
            );
        }
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
     * Returns `true` if `$filename` has a PHP open tag (`<?php') at the start
     * of the first line that is not a shebang (`#!').
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
                extension_loaded('Phar') && Phar::running()) {
            $filename = Convert::resolvePath($filename);

            return file_exists($filename) ? $filename : false;
        }

        return realpath($filename);
    }

    /**
     * Get the URI or filename associated with a stream
     *
     * @param resource $stream A stream opened by `fopen()`, `fsockopen()`,
     * `pfsockopen()` or `stream_socket_client()`.
     * @return string|null `null` if `$stream` is not an open stream resource.
     */
    public static function getStreamUri($stream): ?string
    {
        if (is_resource($stream) && get_resource_type($stream) === 'stream') {
            return stream_get_meta_data($stream)['uri'];
        }

        return null;
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
            $target = 'php://memory';
            $handle = self::open($target, 'w+b');
            $targetName = $target;
            $target = null;
        }

        if ($utf16le) {
            if (!extension_loaded('iconv')) {
                throw new InvalidRuntimeConfigurationException(
                    "'iconv' extension required for UTF-16LE encoding",
                );
            }
            $filter = 'convert.iconv.UTF-8.UTF-16LE';
            $result = stream_filter_append(
                $handle,
                $filter,
                STREAM_FILTER_WRITE,
            );
            if ($result === false) {
                throw new FilesystemErrorException(
                    sprintf('Error applying filter to stream: %s', $filter),
                );
            }
        }

        if ($bom) {
            fwrite($handle, '﻿');
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
                self::fputcsv($handle, array_keys($row), ',', '"', $eol);
            }

            self::fputcsv($handle, $row, ',', '"', $eol);
            $count++;
        }

        if ($target === null) {
            rewind($handle);
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
        string $eol = "\n"
    ): int {
        $special = $separator . $enclosure . "\n\r\t ";
        foreach ($fields as &$field) {
            if (strpbrk((string) $field, $special) !== false) {
                $field = $enclosure
                    . str_replace($enclosure, $enclosure . $enclosure, $field)
                    . $enclosure;
            }
        }

        $written = fwrite($stream, implode($separator, $fields) . $eol);
        if ($written === false) {
            throw new FilesystemErrorException('Error writing to stream');
        }
        return $written;
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
