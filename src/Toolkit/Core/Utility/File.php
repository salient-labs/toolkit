<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Core\Exception\FilesystemErrorException;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\InvalidArgumentTypeException;
use Salient\Core\Exception\InvalidRuntimeConfigurationException;
use Salient\Core\Exception\UnwrittenDataException;
use Salient\Core\AbstractUtility;
use Salient\Core\Indentation;
use Salient\Core\Process;
use Salient\Iterator\RecursiveFilesystemIterator;
use Stringable;

/**
 * Work with files, directories, streams and paths
 *
 * Methods with an optional `$uri` parameter allow the resource URI reported on
 * failure to be overridden.
 */
final class File extends AbstractUtility
{
    private const ABSOLUTE_PATH = <<<'REGEX'
        /^(?:\/|\\\\|[a-z]:[\/\\]|[a-z][-a-z0-9+.]+:)/i
        REGEX;

    /**
     * Check if a path is absolute
     *
     * Returns `true` if `$path` starts with `/`, `\\`, `<letter>:\`,
     * `<letter>:/` or a URI scheme with two or more characters.
     */
    public static function isAbsolute(string $path): bool
    {
        return (bool) Pcre::match(self::ABSOLUTE_PATH, $path);
    }

    /**
     * Check if a path is a "phar://" URI
     */
    public static function isPharUri(string $path): bool
    {
        return Str::lower(substr($path, 0, 7)) === 'phar://';
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
     * Sanitise the name of a directory
     *
     * Returns `"."` if `$directory` is an empty string, otherwise removes
     * trailing directory separators unless `$directory` is comprised entirely
     * of directory separators (e.g. `"/"`).
     */
    public static function dir(string $directory): string
    {
        return Str::coalesce(rtrim($directory, '/\\'), $directory, '.');
    }

    /**
     * Change the current directory
     */
    public static function chdir(string $directory): void
    {
        $result = @chdir($directory);
        self::throwOnFailure($result, 'Error changing directory to: %s', $directory);
    }

    /**
     * Get the current working directory without resolving symbolic links
     */
    public static function getCwd(): string
    {
        $process = Process::withShellCommand(Sys::isWindows() ? 'cd' : 'pwd');
        if ($process->run() === 0) {
            return $process->getOutput();
        }
        error_clear_last();
        $dir = @getcwd();
        return self::throwOnFailure($dir, 'Error getting current working directory');
    }

    /**
     * Close an open stream
     *
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
     * Close a pipe to a process and return its exit status
     *
     * @param resource $pipe
     */
    public static function closePipe($pipe, ?string $command = null): int
    {
        $result = @pclose($pipe);
        if ($result === -1) {
            self::throwOnFailure(false, 'Error closing pipe to process: %s', $command);
        }
        return $result;
    }

    /**
     * Change file permissions
     */
    public static function chmod(string $filename, int $permissions): void
    {
        $result = @chmod($filename, $permissions);
        self::throwOnFailure($result, 'Error changing permissions: %s', $filename);
    }

    /**
     * Copy a file or stream to another file or stream
     *
     * @param Stringable|string|resource $from If `$from` is a seekable stream,
     * it is rewound before copying.
     * @param Stringable|string|resource $to If `$to` is a seekable stream, it
     * is rewound after copying.
     * @param Stringable|string|null $fromUri
     * @param Stringable|string|null $toUri
     */
    public static function copy($from, $to, bool $truncateTo = false, $fromUri = null, $toUri = null): void
    {
        $fromIsResource = is_resource($from);
        $toIsResource = is_resource($to);
        if (
            ($fromIsResource xor $toIsResource) ||
            (Test::isStringable($from) xor Test::isStringable($to))
        ) {
            throw new InvalidArgumentException(
                'Argument #1 ($from) and argument #2 ($to) must both be Stringable|string or resource'
            );
        }

        if ($fromIsResource && $toIsResource) {
            self::assertResourceIsStream($from);
            self::assertResourceIsStream($to);
            self::maybeRewind($from, $fromUri);
            if ($truncateTo) {
                self::truncate($to, 0, $toUri);
            }
            $result = @stream_copy_to_stream($from, $to);
            self::throwOnFailure(
                $result,
                'Error copying stream %s to %s',
                $fromUri,
                $from,
                self::getFriendlyStreamUri($toUri, $to),
            );
            self::maybeRewind($to, $toUri);
            return;
        }

        $from = (string) $from;
        $to = (string) $to;
        $result = @copy($from, $to);
        self::throwOnFailure($result, 'Error copying %s to %s', $from, null, $to);
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
        $result = @touch($filename) && @chmod($filename, $permissions);
        self::throwOnFailure($result, 'Error creating file: %s', $filename);
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
        $parent = dirname($directory);
        $result =
            (is_dir($parent) || @mkdir($parent, 0777, true)) &&
            @mkdir($directory, $permissions) &&
            (!Sys::isWindows() || @chmod($directory, $permissions));
        self::throwOnFailure($result, 'Error creating directory: %s', $directory);
    }

    /**
     * Create a temporary directory
     */
    public static function createTempDir(
        ?string $directory = null,
        ?string $prefix = null
    ): string {
        $directory ??= self::getTempDir();
        $prefix ??= Sys::getProgramBasename();
        do {
            $dir = sprintf(
                '%s/%s%s.tmp',
                Str::coalesce($directory, '.'),
                $prefix,
                Get::randomText(8),
            );
        } while (!@mkdir($dir, 0700));
        if (Sys::isWindows()) {
            self::chmod($dir, 0700);
        }
        return $dir;
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
        $result = @unlink($filename);
        self::throwOnFailure($result, 'Error deleting file: %s', $filename);
    }

    /**
     * Delete a directory if it exists
     *
     * If `$recursive` is `true`, `$directory` and `$setPermissions` are passed
     * to {@see File::pruneDir()} before the directory is deleted.
     */
    public static function deleteDir(
        string $directory,
        bool $recursive = false,
        bool $setPermissions = false
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
            self::pruneDir($directory, $setPermissions);
        }
        $result = @rmdir($directory);
        self::throwOnFailure($result, 'Error deleting directory: %s', $directory);
    }

    /**
     * Get the entire contents of a file or the remaining contents of a stream
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     */
    public static function getContents($resource, ?int $offset = null, $uri = null): string
    {
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            $result = @stream_get_contents($resource, -1, $offset ?? -1);
            return self::throwOnFailure($result, 'Error reading stream: %s', $uri, $resource);
        }
        if (!Test::isStringable($resource)) {
            throw new InvalidArgumentTypeException(1, 'resource', 'Stringable|string|resource', $resource);
        }
        $resource = (string) $resource;
        $result = @file_get_contents($resource, false, null, $offset ?? 0);
        return self::throwOnFailure($result, 'Error reading file: %s', $resource);
    }

    /**
     * Get lines from a file or stream
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     * @return string[]
     */
    public static function getLines($resource, $uri = null): array
    {
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            while (($line = @fgets($resource)) !== false) {
                $lines[] = $line;
            }
            self::checkEof($resource, $uri);
            return $lines ?? [];
        }
        if (!Test::isStringable($resource)) {
            throw new InvalidArgumentTypeException(1, 'resource', 'Stringable|string|resource', $resource);
        }
        $resource = (string) $resource;
        $result = @file($resource);
        return self::throwOnFailure($result, 'Error reading file: %s', $resource);
    }

    /**
     * Read CSV-formatted data from a file or stream
     *
     * @todo Implement file encoding detection
     *
     * @param Stringable|string|resource $resource
     * @return array<mixed[]>
     */
    public static function readCsv($resource): array
    {
        $handle = self::getStream($resource, 'rb', $close, $uri);
        while (($row = @fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $data[] = $row;
        }
        self::checkEof($handle, $uri);
        if ($close) {
            self::close($handle, $uri);
        }
        return $data ?? [];
    }

    /**
     * Open a file or URI
     *
     * @return resource
     */
    public static function open(string $filename, string $mode)
    {
        $stream = @fopen($filename, $mode);
        return self::throwOnFailure($stream, 'Error opening stream: %s', $filename);
    }

    /**
     * Open a pipe to a process
     *
     * @return resource
     */
    public static function openPipe(string $command, string $mode)
    {
        $pipe = @popen($command, $mode);
        return self::throwOnFailure($pipe, 'Error opening pipe to process: %s', $command);
    }

    /**
     * Read from an open stream
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function read($stream, int $length, $uri = null): string
    {
        $data = @fread($stream, $length);
        return self::throwOnFailure($data, 'Error reading from stream: %s', $uri, $stream);
    }

    /**
     * Read a line from an open stream
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function readLine($stream, $uri = null): string
    {
        $line = @fgets($stream);
        if ($line !== false) {
            return $line;
        }
        self::checkEof($stream, $uri);
        return '';
    }

    /**
     * Resolve symbolic links and relative references in a path or Phar URI
     *
     * An exception is thrown if `$path` does not exist.
     */
    public static function realpath(string $path): string
    {
        if (self::isPharUri($path) && file_exists($path)) {
            return self::resolve($path, true);
        }
        $_path = $path;
        error_clear_last();
        $path = @realpath($path);
        return self::throwOnFailure($path, 'Error resolving path: %s', $_path);
    }

    /**
     * Rewind to the beginning of a stream
     *
     * Equivalent to `File::seek($stream, 0, \SEEK_SET, $uri)`.
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function rewind($stream, $uri = null): void
    {
        self::seek($stream, 0, \SEEK_SET, $uri);
    }

    /**
     * Rewind to the beginning of a stream if it is seekable
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function maybeRewind($stream, $uri = null): void
    {
        if (self::isSeekable($stream)) {
            self::seek($stream, 0, \SEEK_SET, $uri);
        }
    }

    /**
     * Check if two paths refer to the same filesystem entry
     */
    public static function same(string $filename1, string $filename2): bool
    {
        if (!file_exists($filename1)) {
            return false;
        }

        if ($filename1 === $filename2) {
            return true;
        }

        if (!file_exists($filename2)) {
            return false;
        }

        $stat1 = self::stat($filename1);
        $stat2 = self::stat($filename2);

        return
            $stat1['dev'] === $stat2['dev'] &&
            $stat1['ino'] === $stat2['ino'];
    }

    /**
     * Set the file position indicator for a stream
     *
     * @param resource $stream
     * @param \SEEK_SET|\SEEK_CUR|\SEEK_END $whence
     * @param Stringable|string|null $uri
     */
    public static function seek($stream, int $offset, int $whence = \SEEK_SET, $uri = null): void
    {
        $result = @fseek($stream, $offset, $whence);
        if ($result === -1) {
            self::throwOnFailure(false, 'Error setting file position indicator for stream: %s', $uri, $stream);
        }
    }

    /**
     * Set the file position indicator for a stream if it is seekable
     *
     * @param resource $stream
     * @param \SEEK_SET|\SEEK_CUR|\SEEK_END $whence
     * @param Stringable|string|null $uri
     */
    public static function maybeSeek($stream, int $offset, int $whence = \SEEK_SET, $uri = null): void
    {
        if (self::isSeekable($stream)) {
            self::seek($stream, $offset, $whence, $uri);
        }
    }

    /**
     * Get the size of a file
     *
     * @phpstan-impure
     */
    public static function size(string $filename): int
    {
        $size = @filesize($filename);
        return self::throwOnFailure($size, 'Error getting file size: %s', $filename);
    }

    /**
     * Get the status of a file or stream
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     * @return int[]
     */
    public static function stat($resource, $uri = null): array
    {
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            $result = @fstat($resource);
            return self::throwOnFailure($result, 'Error getting status of stream: %s', $uri, $resource);
        }
        if (!Test::isStringable($resource)) {
            throw new InvalidArgumentTypeException(1, 'resource', 'Stringable|string|resource', $resource);
        }
        $resource = (string) $resource;
        $result = @stat($resource);
        return self::throwOnFailure($result, 'Error getting file status: %s', $resource);
    }

    /**
     * Get the file position indicator for a stream
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function tell($stream, $uri = null): int
    {
        $result = @ftell($stream);
        return self::throwOnFailure($result, 'Error getting file position indicator for stream: %s', $uri, $stream);
    }

    /**
     * Rewind to the beginning of a stream and truncate it
     *
     * @param resource $stream
     * @param int<0,max> $size
     * @param Stringable|string|null $uri
     */
    public static function truncate($stream, int $size = 0, $uri = null): void
    {
        self::seek($stream, 0, \SEEK_SET, $uri);
        $result = @ftruncate($stream, $size);
        self::throwOnFailure($result, 'Error truncating stream: %s', $uri, $stream);
    }

    /**
     * Get the type of a file
     *
     * @return ("fifo"|"char"|"dir"|"block"|"link"|"file"|"socket"|"unknown")
     */
    public static function type(string $filename): string
    {
        $type = @filetype($filename);
        /** @var ("fifo"|"char"|"dir"|"block"|"link"|"file"|"socket"|"unknown") */
        return self::throwOnFailure($type, 'Error getting file type: %s', $filename);
    }

    /**
     * Write to an open stream
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     * @throws UnwrittenDataException when fewer bytes are written than given.
     */
    public static function write($stream, string $data, ?int $length = null, $uri = null): int
    {
        $result = self::doWrite($stream, $data, $length, $unwritten, $uri);
        if ($unwritten > 0) {
            throw new UnwrittenDataException(Inflect::format(
                $unwritten,
                'Error writing to stream: {{#}} {{#:byte}} not written to %s',
                self::getFriendlyStreamUri($uri, $stream),
            ));
        }
        return $result;
    }

    /**
     * Write to an open stream and apply any unwritten data to a buffer
     *
     * @param resource $stream
     * @param-out string $buffer
     * @param Stringable|string|null $uri
     */
    public static function writeWithBuffer($stream, string $data, ?string &$buffer, ?int $length = null, $uri = null): int
    {
        $result = self::doWrite($stream, $data, $length, $unwritten, $uri);
        $buffer = substr($data, $result);
        return $result;
    }

    /**
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    private static function doWrite($stream, string $data, ?int $length, ?int &$unwritten, $uri): int
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
        assert($result <= $expected);
        $unwritten = $expected - $result;
        return $result;
    }

    /**
     * Write data to a file
     *
     * @param resource|array<int|float|string|bool|Stringable|null>|string $data
     * @param int-mask-of<\FILE_USE_INCLUDE_PATH|\FILE_APPEND|\LOCK_EX> $flags
     */
    public static function putContents(string $filename, $data, int $flags = 0): int
    {
        $result = @file_put_contents($filename, $data, $flags);
        return self::throwOnFailure($result, 'Error writing file: %s', $filename);
    }

    /**
     * Write CSV-formatted data to a file or stream
     *
     * For maximum interoperability with Excel across all platforms, output is
     * written in UTF-16LE with a BOM (byte order mark) by default.
     *
     * @template TValue
     *
     * @param Stringable|string|resource $resource
     * @param iterable<TValue> $data
     * @param bool $headerRow Write the first entry's keys before the first row
     * of data.
     * @param int|float|string|bool|null $nullValue Replace `null` values before
     * writing data.
     * @param (callable(TValue): mixed[])|null $callback Apply a callback to
     * each entry before it is written.
     * @param int|null $count Receives the number of entries written.
     * @param-out int $count
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
        $handle = self::getStream($resource, 'wb', $close, $uri);

        if ($utf16le) {
            if (!extension_loaded('iconv')) {
                throw new InvalidRuntimeConfigurationException(
                    "'iconv' extension required for UTF-16LE encoding"
                );
            }
            $filter = @stream_filter_append($handle, 'convert.iconv.UTF-8.UTF-16LE', \STREAM_FILTER_WRITE);
            self::throwOnFailure($filter, 'Error applying UTF-16LE filter to stream: %s', $uri, $handle);
        }

        if ($bom) {
            self::write($handle, "\u{FEFF}", null, $uri);
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
     * Check if a path exists and is writable, or doesn't exist but descends
     * from a writable directory
     */
    public static function creatable(string $path): bool
    {
        $path = self::existing($path);
        return $path !== null && is_writable($path);
    }

    /**
     * Check if a value is a seekable stream
     *
     * @param mixed $value
     * @phpstan-assert-if-true resource $value
     */
    public static function isSeekable($value): bool
    {
        return self::isStream($value) &&
            // @phpstan-ignore-next-line
            (stream_get_meta_data($value)['seekable'] ?? false);
    }

    /**
     * Check if a value is a stream resource
     *
     * @param mixed $value
     * @phpstan-assert-if-true resource $value
     */
    public static function isStream($value): bool
    {
        return is_resource($value) && get_resource_type($value) === 'stream';
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
     * Get a path or its closest parent that exists
     *
     * Returns `null` if the leftmost segment of `$path` doesn't exist, or if
     * the closest parent that exists is not a directory.
     */
    public static function existing(string $path): ?string
    {
        $pathIsParent = false;
        while (!file_exists($path)) {
            $parent = dirname($path);
            if ($parent === $path) {
                // @codeCoverageIgnoreStart
                return null;
                // @codeCoverageIgnoreEnd
            }
            $path = $parent;
            $pathIsParent = true;
        }

        if ($pathIsParent && !is_dir($path)) {
            return null;
        }

        return $path;
    }

    /**
     * If a stream is not seekable, copy it to a temporary stream that is and
     * close it
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     * @return resource
     */
    public static function getSeekable($stream, $uri = null)
    {
        if (self::isSeekable($stream)) {
            return $stream;
        }
        $seekable = self::open('php://temp', 'r+');
        self::copy($stream, $seekable, false, $uri);
        self::close($stream, $uri);
        return $seekable;
    }

    /**
     * Generate a filename unique to the current user and the path of the
     * running script
     *
     * If `$dir` is not given, a filename in {@see sys_get_temp_dir()} is
     * returned.
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
        $hash = Get::hash($path);
        $user = Sys::getUserId();

        if ($dir === null) {
            $dir = self::getTempDir();
        } else {
            $dir = self::dir($dir);
        }

        return sprintf('%s/%s-%s-%s%s', $dir, $program, $hash, $user, $suffix);
    }

    /**
     * Get the URI associated with a stream
     *
     * @param resource $stream
     * @return string|null `null` if `$stream` is closed or does not have a URI.
     */
    public static function getStreamUri($stream): ?string
    {
        if (self::isStream($stream)) {
            // @phpstan-ignore-next-line
            return stream_get_meta_data($stream)['uri'] ?? null;
        }
        return null;
    }

    /**
     * Recursively delete the contents of a directory without deleting the
     * directory itself
     *
     * If `$setPermissions` is `true`, file modes in `$directory` are changed if
     * necessary for deletion to succeed.
     */
    public static function pruneDir(string $directory, bool $setPermissions = false): void
    {
        $files = (new RecursiveFilesystemIterator())
            ->in($directory)
            ->dirs();

        $windows = false;
        if ($setPermissions) {
            $windows = Sys::isWindows();
            clearstatcache();
            // With exceptions `chmod()` can't address:
            // - On *nix, filesystem entries can be deleted if their parent
            //   directory is writable
            // - On Windows, they can be deleted if they are writable, whether
            //   their parent directory is writable or not
            if (!$windows) {
                foreach ($files->noFiles() as $dir) {
                    if (
                        $dir->isReadable() &&
                        $dir->isWritable() &&
                        $dir->isExecutable()
                    ) {
                        continue;
                    }
                    $perms = @$dir->getPerms();
                    if ($perms === false) {
                        // @codeCoverageIgnoreStart
                        $perms = 0;
                        // @codeCoverageIgnoreEnd
                    }
                    self::chmod((string) $dir, $perms | 0700);
                }
            }
        }

        foreach ($files->dirsLast() as $file) {
            if ($windows && !$file->isWritable()) {
                self::chmod((string) $file, $file->isDir() ? 0700 : 0600);
            }
            $result = $file->isDir()
                ? @rmdir((string) $file)
                : @unlink((string) $file);
            self::throwOnFailure($result, 'Error pruning directory: %s', $directory);
        }
    }

    /**
     * Get a path relative to a parent directory
     *
     * Returns `$fallback` if `$filename` does not belong to `$parentDir`.
     *
     * An exception is thrown if `$filename` or `$parentDir` do not exist.
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
     * Get the end-of-line sequence used in a file or stream
     *
     * Recognised line endings are LF (`"\n"`), CRLF (`"\r\n"`) and CR (`"\r"`).
     *
     * @see Get::eol()
     * @see Str::setEol()
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     * @return string|null `null` if there are no recognised line breaks in the
     * file.
     */
    public static function getEol($resource, $uri = null): ?string
    {
        $handle = self::getStream($resource, 'r', $close, $uri);
        $line = self::readLine($handle);
        if ($close) {
            self::close($handle, $uri);
        }

        if ($line === '') {
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
     * Guess the indentation used in a file or stream
     *
     * Derived from VS Code's `indentationGuesser`.
     *
     * Returns `$default` if `$resource` appears to use the default indentation.
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     *
     * @link https://github.com/microsoft/vscode/blob/860d67064a9c1ef8ce0c8de35a78bea01033f76c/src/vs/editor/common/model/indentationGuesser.ts
     */
    public static function guessIndentation(
        $resource,
        ?Indentation $default = null,
        bool $alwaysGuessTabSize = false,
        $uri = null
    ): Indentation {
        $handle = self::getStream($resource, 'r', $close, $uri);

        $lines = 0;
        $linesWithTabs = 0;
        $linesWithSpaces = 0;
        $diffSpacesCount = [2 => 0, 0, 0, 0, 0, 0, 0];

        $prevLine = '';
        $prevOffset = 0;
        while ($lines < 10000) {
            $line = @fgets($handle);
            if ($line === false) {
                self::checkEof($handle, $uri);
                break;
            }

            $lines++;

            $line = rtrim($line);
            if ($line === '') {
                continue;
            }

            $length = strlen($line);
            $spaces = 0;
            $tabs = 0;
            for ($offset = 0; $offset < $length; $offset++) {
                if ($line[$offset] === "\t") {
                    $tabs++;
                } elseif ($line[$offset] === ' ') {
                    $spaces++;
                } else {
                    break;
                }
            }

            if ($tabs) {
                $linesWithTabs++;
            } elseif ($spaces > 1) {
                $linesWithSpaces++;
            }

            $minOffset = $prevOffset < $offset ? $prevOffset : $offset;
            for ($i = 0; $i < $minOffset; $i++) {
                if ($prevLine[$i] !== $line[$i]) {
                    break;
                }
            }

            $prevLineSpaces = 0;
            $prevLineTabs = 0;
            for ($j = $i; $j < $prevOffset; $j++) {
                if ($prevLine[$j] === ' ') {
                    $prevLineSpaces++;
                } else {
                    $prevLineTabs++;
                }
            }

            $lineSpaces = 0;
            $lineTabs = 0;
            for ($j = $i; $j < $offset; $j++) {
                if ($line[$j] === ' ') {
                    $lineSpaces++;
                } else {
                    $lineTabs++;
                }
            }

            $_prevLine = $prevLine;
            $_prevOffset = $prevOffset;
            $_line = $line;

            $prevLine = $line;
            $prevOffset = $offset;

            if (
                ($prevLineSpaces && $prevLineTabs) ||
                ($lineSpaces && $lineTabs)
            ) {
                continue;
            }

            $diffSpaces = abs($prevLineSpaces - $lineSpaces);
            $diffTabs = abs($prevLineTabs - $lineTabs);
            if (!$diffTabs) {
                // Skip if the difference could be alignment-related and doesn't
                // match the file's default indentation
                if (
                    $diffSpaces &&
                    $lineSpaces &&
                    $lineSpaces - 1 < strlen($_prevLine) &&
                    $_line[$lineSpaces] !== ' ' &&
                    $_prevLine[$lineSpaces - 1] === ' ' &&
                    $_prevLine[-1] === ',' && !(
                        $default &&
                        $default->InsertSpaces &&
                        $default->TabSize === $diffSpaces
                    )
                ) {
                    $prevLine = $_prevLine;
                    $prevOffset = $_prevOffset;
                    continue;
                }
            } elseif ($diffSpaces % $diffTabs === 0) {
                $diffSpaces /= $diffTabs;
            } else {
                continue;
            }

            if ($diffSpaces > 1 && $diffSpaces <= 8) {
                $diffSpacesCount[$diffSpaces]++;
            }
        }

        $insertSpaces = $linesWithTabs === $linesWithSpaces
            ? $default->InsertSpaces ?? true
            : $linesWithTabs < $linesWithSpaces;

        $tabSize = $default->TabSize ?? 4;

        // Only guess tab size if inserting spaces
        if ($insertSpaces || $alwaysGuessTabSize) {
            $count = 0;
            foreach ([2, 4, 6, 8, 3, 5, 7] as $diffSpaces) {
                if ($diffSpacesCount[$diffSpaces] > $count) {
                    $tabSize = $diffSpaces;
                    $count = $diffSpacesCount[$diffSpaces];
                }
            }
        }

        if ($close) {
            self::close($handle, $uri);
        }

        if (
            $default &&
            $default->InsertSpaces === $insertSpaces &&
            $default->TabSize === $tabSize
        ) {
            return $default;
        }

        return new Indentation($insertSpaces, $tabSize);
    }

    /**
     * Check if a file or stream appears to contain PHP code
     *
     * Returns `true` if `$resource` has a PHP open tag (`<?php`) at the start
     * of the first line that is not a shebang (`#!`).
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     */
    public static function isPhp($resource, $uri = null): bool
    {
        $handle = self::getStream($resource, 'r', $close, $uri);
        $line = self::readLine($handle);
        if ($line !== '' && substr($line, 0, 2) === '#!') {
            $line = self::readLine($handle);
        }
        if ($close) {
            self::close($handle, $uri);
        }

        if ($line === '') {
            return false;
        }

        return (bool) Pcre::match('/^<\?(php\s|(?!php|xml\s))/', $line);
    }

    /**
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    private static function checkEof($stream, $uri = null): void
    {
        $error = error_get_last();
        if (@feof($stream)) {
            return;
        }
        if ($error) {
            throw new FilesystemErrorException($error['message']);
        }
        throw new FilesystemErrorException(sprintf(
            'Error reading from %s',
            self::getFriendlyStreamUri($uri, $stream),
        ));
    }

    /**
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     * @param-out bool $close
     * @param-out Stringable|string|null $uri
     * @return resource
     */
    private static function getStream($resource, string $mode, ?bool &$close, &$uri)
    {
        $close = false;
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            return $resource;
        }
        if (Test::isStringable($resource)) {
            $uri = (string) $resource;
            $close = true;
            return self::open($uri, $mode);
        }
        throw new InvalidArgumentTypeException(1, 'resource', 'Stringable|string|resource', $resource);
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
     * @template T
     *
     * @param T $result
     * @param Stringable|string|null $uri
     * @param resource|null $stream
     * @param string|int|float ...$args
     * @return (T is false ? never : T)
     * @phpstan-param T|false $result
     * @phpstan-return ($result is false ? never : T)
     */
    private static function throwOnFailure($result, string $message, $uri = null, $stream = null, ...$args)
    {
        if ($result === false) {
            $error = error_get_last();
            if ($error) {
                throw new FilesystemErrorException($error['message']);
            }
            throw new FilesystemErrorException(
                sprintf($message, self::getFriendlyStreamUri($uri, $stream), ...$args)
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
        return $uri ?? '<stream>';
    }
}
