<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Exception\Exception;
use Lkrms\Facade\Console;
use Lkrms\Facade\Sys;
use Lkrms\Iterator\RecursiveFilesystemIterator;
use Lkrms\Utility\Compute;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Test;
use Phar;
use SplFileInfo;

/**
 * Work with files, directories and paths
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
            throw new Exception(sprintf('Error opening file: %s', $filename));
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
            throw new Exception(sprintf('Error closing file: %s', $filename));
        }
    }

    /**
     * Iterate over files in one or more directories
     *
     * @param string[]|string|null $directory
     * @param array<string,callable(SplFileInfo): bool> $excludeCallbacks
     * @param array<string,callable(SplFileInfo): bool> $includeCallbacks
     */
    public static function find(
        $directory = null,
        ?string $exclude = null,
        ?string $include = null,
        ?array $excludeCallbacks = null,
        ?array $includeCallbacks = null,
        bool $recursive = true,
        bool $withDirectories = false,
        bool $withDirectoriesFirst = true
    ): RecursiveFilesystemIterator {
        $directory = (array) $directory;

        $iterator =
            (new RecursiveFilesystemIterator())
                ->in(...$directory)
                ->recurse($recursive)
                ->dirs($withDirectories)
                ->dirsFirst($withDirectoriesFirst);

        if ($exclude !== null) {
            $iterator = $iterator->exclude($exclude);
        }

        if ($include !== null) {
            $iterator = $iterator->include($include);
        }

        if ($excludeCallbacks) {
            foreach ($excludeCallbacks as $regex => $callback) {
                $iterator = $iterator->exclude(
                    fn(SplFileInfo $current, string $key) =>
                        Pcre::match($regex, $key) ||
                        ($current->isDir() && Pcre::match($regex, "{$key}/"))
                            ? $callback($current)
                            : false
                );
            }
        }

        if ($includeCallbacks) {
            foreach ($includeCallbacks as $regex => $callback) {
                $iterator = $iterator->include(
                    fn(SplFileInfo $current, string $key) =>
                        Pcre::match($regex, $key) ||
                        ($current->isDir() && Pcre::match($regex, "{$key}/"))
                            ? $callback($current)
                            : false
                );
            }
        }

        return $iterator;
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
        $f = self::open($filename, 'r');
        $line = fgets($f);
        self::close($f, $filename);

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
     * @param string $filename
     */
    public static function isPhp(string $filename): bool
    {
        $f = self::open($filename, 'r');
        $line = fgets($f);
        if ($line !== false && substr($line, 0, 2) === '#!') {
            $line = fgets($f);
        }
        self::close($f, $filename);

        if ($line === false) {
            return false;
        }

        return (bool) Pcre::match('/^<\?(php\s|(?!php|xml\s))/', $line);
    }

    /**
     * Create a file if it doesn't exist
     *
     * @param string $filename Full path to the file.
     * @param int $permissions Only used if `$filename` needs to be created.
     * @param int $dirPermissions Only used if `$filename`'s parent directory
     * needs to be created.
     * @return bool `true` on success or `false` on failure.
     */
    public static function maybeCreate(string $filename, int $permissions = 0777, int $dirPermissions = 0777): bool
    {
        $dir = dirname($filename);

        if ((is_dir($dir) || mkdir($dir, $dirPermissions, true)) &&
                (is_file($filename) || (touch($filename) && chmod($filename, $permissions)))) {
            return true;
        }

        return false;
    }

    /**
     * Create a directory if it doesn't exist
     *
     * @param string $directory Full path to the directory.
     * @param int $permissions Only used if `$filename` needs to be created.
     * @return bool `true` on success or `false` on failure.
     */
    public static function maybeCreateDirectory(string $directory, int $permissions = 0777): bool
    {
        if (is_dir($directory) || mkdir($directory, $permissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Delete a file if it exists
     *
     * @return bool `true` on success or `false` on failure.
     */
    public static function maybeDelete(string $filename): bool
    {
        if (!file_exists($filename)) {
            return true;
        }
        if (!is_file($filename)) {
            Console::warn('Not a file:', $filename);
            return false;
        }

        return unlink($filename);
    }

    /**
     * Delete a directory if it exists
     *
     * @return bool `true` on success or `false` on failure.
     */
    public static function maybeDeleteDirectory(string $directory, bool $recursive = false): bool
    {
        if (!file_exists($directory)) {
            return true;
        }
        if (!is_dir($directory)) {
            Console::warn('Not a directory:', $directory);
            return false;
        }

        return (!$recursive || self::pruneDirectory($directory)) &&
            rmdir($directory);
    }

    /**
     * Recursively delete the contents of a directory without deleting the
     * directory itself
     *
     * @return bool `true` on success or `false` on failure.
     */
    public static function pruneDirectory(string $directory): bool
    {
        (new RecursiveFilesystemIterator())
            ->in($directory)
            ->dirs()
            ->dirsLast()
            ->forEachWhile(
                fn(SplFileInfo $file) =>
                    $file->isDir()
                        ? rmdir((string) $file)
                        : unlink((string) $file),
                $result
            );

        return $result;
    }

    /**
     * Create a temporary directory
     */
    public static function createTemporaryDirectory(): string
    {
        $tmp = realpath($_tmp = sys_get_temp_dir());
        if ($tmp === false || !is_dir($tmp) || !is_writable($tmp)) {
            throw new Exception(sprintf('Not a writable directory: %s', $_tmp));
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
     *      by {@see Conversions::resolvePath()}
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
        if (is_resource($stream) && get_resource_type($stream) == 'stream') {
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
            if (($type = get_resource_type($target)) !== 'stream') {
                throw new Exception(sprintf('Invalid resource type: %s', $type));
            }
            $f = $target;
            $targetName = 'stream';
        } elseif (is_string($target)) {
            $f = self::open($target, 'wb');
            $targetName = $target;
        } else {
            $target = 'php://memory';
            $f = self::open($target, 'w+b');
            $targetName = $target;
            $target = null;
        }

        if ($utf16le) {
            if (extension_loaded('iconv')) {
                stream_filter_append($f, 'convert.iconv.UTF-8.UTF-16LE', STREAM_FILTER_WRITE);
            } else {
                Console::warnOnce("'iconv' extension required for UTF-16LE encoding");
            }
        }

        if ($bom) {
            fwrite($f, '﻿');
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
                self::fputcsv($f, array_keys($row), ',', '"', $eol);
            }

            self::fputcsv($f, $row, ',', '"', $eol);
            $count++;
        }

        if ($target === null) {
            rewind($f);
            $csv = stream_get_contents($f);
            self::close($f, $targetName);

            return $csv;
        }
        if (is_string($target)) {
            self::close($f, $targetName);
        }

        return true;
    }

    /**
     * A polyfill for PHP 8.1's fputcsv, minus $escape
     *
     * @param resource $stream
     * @param mixed[] $fields
     * @return int|false
     */
    private static function fputcsv(
        $stream,
        array $fields,
        string $separator = ',',
        string $enclosure = '"',
        string $eol = "\n"
    ) {
        $special = $separator . $enclosure . "\n\r\t ";
        foreach ($fields as &$field) {
            if (strpbrk((string) $field, $special) !== false) {
                $field = $enclosure
                    . str_replace($enclosure, $enclosure . $enclosure, $field)
                    . $enclosure;
            }
        }
        return fwrite($stream, implode($separator, $fields) . $eol);
    }

    /**
     * Generate a filename unique to the current user and the path of the
     * running script
     *
     * If `$dir` is not given, a filename in `sys_get_temp_dir()` is returned.
     *
     * No changes are made to the filesystem.
     */
    public static function getStablePath(string $suffix = '', ?string $dir = null): string
    {
        $path = self::realpath($program = Sys::getProgramName());
        if ($path === false) {
            throw new Exception('Unable to resolve filename used to run the script');
        }
        $program = basename($program);
        $hash = Compute::hash($path);
        if (function_exists('posix_geteuid')) {
            $user = posix_geteuid();
        } else {
            $user = Env::get('USERNAME', null) ?: Env::get('USER', null);
            if (!$user) {
                throw new Exception('Unable to identify user');
            }
        }
        if ($dir === null) {
            $dir = realpath($tmp = sys_get_temp_dir());
            if ($dir === false || !is_dir($dir) || !is_writable($dir)) {
                throw new Exception(sprintf('Not a writable directory: %s', $tmp));
            }
        } else {
            $dir = rtrim($dir, '/\\') ?: $dir;
        }

        return sprintf('%s/%s-%s-%s%s', $dir ?: '.', $program, $hash, $user, $suffix);
    }
}
