<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Salient\Core\Exception\FilesystemErrorException;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\InvalidArgumentTypeException;
use Salient\Core\Exception\InvalidRuntimeConfigurationException;
use Salient\Core\Exception\UnreadDataException;
use Salient\Core\Exception\UnwrittenDataException;
use Salient\Core\AbstractUtility;
use Salient\Core\Process;
use Salient\Iterator\RecursiveFilesystemIterator;
use Stringable;

/**
 * Work with files, directories, streams and paths
 *
 * Methods with an optional `$uri` parameter allow the resource URI reported on
 * failure to be overridden.
 *
 * @api
 */
final class File extends AbstractUtility
{
    /**
     * Check if a path is absolute
     *
     * Returns `true` if `$path` starts with `/`, `\\`, `<letter>:\`,
     * `<letter>:/` or a URI scheme with two or more characters.
     */
    public static function isAbsolute(string $path): bool
    {
        return (bool) Pcre::match(
            '@^(?:/|\\\\\\\\|[a-z]:[/\\\\]|[a-z][-a-z0-9+.]+:)@i',
            $path,
        );
    }

    /**
     * Check if a path is a "phar://" URI
     */
    public static function isPharUri(string $path): bool
    {
        return Str::lower(substr($path, 0, 7)) === 'phar://';
    }

    /**
     * Resolve "/./" and "/../" segments in a path without accessing the
     * filesystem
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
     * echo File::resolvePath('/dir/subdir//../') . PHP_EOL;
     * echo File::resolvePath('/dir/subdir//../', true) . PHP_EOL;
     * ```
     *
     * Output:
     *
     * ```
     * /dir/
     * /dir/subdir/
     * ```
     */
    public static function resolvePath(string $path, bool $withEmptySegments = false): string
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
     * Sanitise a path to a directory
     *
     * Returns `"."` if `$directory` is an empty string, otherwise removes
     * trailing directory separators.
     */
    public static function sanitiseDir(string $directory): string
    {
        return $directory === ''
            ? '.'
            : Str::coalesce(
                rtrim($directory, \DIRECTORY_SEPARATOR === '/' ? '/' : '\/'),
                \DIRECTORY_SEPARATOR,
            );
    }

    /**
     * Change the current directory
     */
    public static function chdir(string $directory): void
    {
        $result = @chdir($directory);
        self::maybeThrow($result, 'Error changing directory to: %s', $directory);
    }

    /**
     * Change file permissions
     */
    public static function chmod(string $filename, int $permissions): void
    {
        $result = @chmod($filename, $permissions);
        self::maybeThrow($result, 'Error changing permissions: %s', $filename);
    }

    /**
     * Get a path or its closest parent that exists
     *
     * Returns `null` if the leftmost segment of `$path` doesn't exist, or if
     * the closest parent that exists is not a directory.
     */
    public static function closestExisting(string $path): ?string
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
     * Create a file if it doesn't exist
     *
     * @param int $permissions Applied when creating `$filename`.
     * @param int $dirPermissions Applied when creating `$filename`'s directory.
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
        self::maybeThrow($result, 'Error creating file: %s', $filename);
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
            (is_dir($parent) || @mkdir($parent, 0777, true))
            && @mkdir($directory, $permissions)
            && @chmod($directory, $permissions);
        self::maybeThrow($result, 'Error creating directory: %s', $directory);
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
        self::chmod($dir, 0700);
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
        self::maybeThrow($result, 'Error deleting file: %s', $filename);
    }

    /**
     * Delete a directory if it exists
     */
    public static function deleteDir(string $directory): void
    {
        if (!file_exists($directory)) {
            return;
        }
        if (!is_dir($directory)) {
            throw new FilesystemErrorException(
                sprintf('Not a directory: %s', $directory),
            );
        }
        $result = @rmdir($directory);
        self::maybeThrow($result, 'Error deleting directory: %s', $directory);
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
     * Get the current working directory without resolving symbolic links
     */
    public static function getcwd(): string
    {
        $process = Process::withShellCommand(Sys::isWindows() ? 'cd' : 'pwd');
        if ($process->run() === 0) {
            return $process->getText();
        }
        error_clear_last();
        $dir = @getcwd();
        return self::maybeThrow($dir, 'Error getting current working directory');
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
            $dir = self::sanitiseDir($dir);
        }

        return sprintf('%s/%s-%s-%s%s', $dir, $program, $hash, $user, $suffix);
    }

    /**
     * Check if a path exists and is writable, or doesn't exist but descends
     * from a writable directory
     */
    public static function isCreatable(string $path): bool
    {
        $path = self::closestExisting($path);
        return $path !== null && is_writable($path);
    }

    /**
     * Recursively delete the contents of a directory before optionally deleting
     * the directory itself
     *
     * If `$setPermissions` is `true`, file modes in `$directory` are changed if
     * necessary for deletion to succeed.
     */
    public static function pruneDir(string $directory, bool $delete = false, bool $setPermissions = false): void
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
                        $dir->isReadable()
                        && $dir->isWritable()
                        && $dir->isExecutable()
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
            self::maybeThrow($result, 'Error pruning directory: %s', $directory);
        }

        if ($delete) {
            self::deleteDir($directory);
        }
    }

    /**
     * Resolve symbolic links and relative references in a path or Phar URI
     *
     * An exception is thrown if `$path` does not exist.
     */
    public static function realpath(string $path): string
    {
        if (self::isPharUri($path) && file_exists($path)) {
            return self::resolvePath($path, true);
        }
        $_path = $path;
        error_clear_last();
        $path = @realpath($path);
        return self::maybeThrow($path, 'Error resolving path: %s', $_path);
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
            $stat1['dev'] === $stat2['dev']
            && $stat1['ino'] === $stat2['ino'];
    }

    /**
     * Get the size of a file
     *
     * @phpstan-impure
     */
    public static function size(string $filename): int
    {
        $size = @filesize($filename);
        return self::maybeThrow($size, 'Error getting file size: %s', $filename);
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
        return self::maybeThrow($type, 'Error getting file type: %s', $filename);
    }

    /**
     * Write data to a file
     *
     * @param resource|array<int|float|string|bool|Stringable|null>|string $data
     * @param int-mask-of<\FILE_USE_INCLUDE_PATH|\FILE_APPEND|\LOCK_EX> $flags
     */
    public static function writeContents(string $filename, $data, int $flags = 0): int
    {
        $result = @file_put_contents($filename, $data, $flags);
        return self::maybeThrow($result, 'Error writing file: %s', $filename);
    }

    /**
     * Check for errors after fgets(), fgetcsv(), etc. return false
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function checkEof($stream, $uri = null): void
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
     * Close an open stream
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function close($stream, $uri = null): void
    {
        $uri = self::getFriendlyStreamUri($uri, $stream);
        $result = @fclose($stream);
        self::maybeThrow($result, 'Error closing stream: %s', $uri);
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
            self::maybeThrow(false, 'Error closing pipe to process: %s', $command);
        }
        return $result;
    }

    /**
     * If a stream is not seekable, copy it to a temporary stream that is and
     * close it
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     * @return resource
     */
    public static function getSeekableStream($stream, $uri = null)
    {
        if (self::isSeekableStream($stream)) {
            return $stream;
        }
        $seekable = self::open('php://temp', 'r+');
        self::copy($stream, $seekable, $uri);
        self::close($stream, $uri);
        self::rewind($seekable);
        return $seekable;
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
     * Check if a value is a seekable stream resource
     *
     * @param mixed $value
     * @phpstan-assert-if-true resource $value
     */
    public static function isSeekableStream($value): bool
    {
        return self::isStream($value)
            // @phpstan-ignore-next-line
            && (stream_get_meta_data($value)['seekable'] ?? false);
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
     * Open a file or URI
     *
     * @return resource
     */
    public static function open(string $filename, string $mode)
    {
        $stream = @fopen($filename, $mode);
        return self::maybeThrow($stream, 'Error opening stream: %s', $filename);
    }

    /**
     * Open a resource if it is not already open
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     * @param-out bool $close
     * @param-out Stringable|string|null $uri
     * @return resource
     */
    public static function maybeOpen($resource, string $mode, ?bool &$close, &$uri)
    {
        $close = false;
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            return $resource;
        }
        self::assertResourceIsStringable($resource);
        $uri = (string) $resource;
        $close = true;
        return self::open($uri, $mode);
    }

    /**
     * Open a pipe to a process
     *
     * @return resource
     */
    public static function openPipe(string $command, string $mode)
    {
        $pipe = @popen($command, $mode);
        return self::maybeThrow($pipe, 'Error opening pipe to process: %s', $command);
    }

    /**
     * Read from an open stream
     *
     * @param resource $stream
     * @param int<0,max> $length
     * @param Stringable|string|null $uri
     */
    public static function read($stream, int $length, $uri = null): string
    {
        $data = @fread($stream, $length);
        return self::maybeThrow($data, 'Error reading from stream: %s', $uri, $stream);
    }

    /**
     * Read from an open stream until data of the expected length is read
     *
     * @param resource $stream
     * @param int<0,max> $length
     * @param Stringable|string|null $uri
     * @throws UnreadDataException when fewer bytes are read than expected and
     * the stream is at end-of-file.
     */
    public static function readAll($stream, int $length, $uri = null): string
    {
        if ($length === 0) {
            return '';
        }
        $data = '';
        $dataLength = 0;
        do {
            assert($length - $dataLength > 0);
            $result = self::read($stream, $length - $dataLength, $uri);
            if ($result === '') {
                if (@feof($stream)) {
                    break;
                }
                usleep(10000);
                continue;
            }
            $data .= $result;
            $dataLength += strlen($result);
            if ($dataLength === $length) {
                return $data;
            }
            // Minimise CPU usage, e.g. when reading from non-blocking streams
            usleep(10000);
        } while (true);

        throw new UnreadDataException(Inflect::format(
            $length - $dataLength,
            'Error reading from stream: expected {{#}} more {{#:byte}} from %s',
            self::getFriendlyStreamUri($uri, $stream),
        ));
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
     * Rewind to the beginning of a stream
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function rewind($stream, $uri = null): void
    {
        $result = rewind($stream);
        self::maybeThrow($result, 'Error rewinding file position indicator for stream: %s', $uri, $stream);
    }

    /**
     * Rewind to the beginning of a stream if it is seekable
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function maybeRewind($stream, $uri = null): void
    {
        if (self::isSeekableStream($stream)) {
            self::rewind($stream, $uri);
        }
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
            self::maybeThrow(false, 'Error setting file position indicator for stream: %s', $uri, $stream);
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
        if (self::isSeekableStream($stream)) {
            self::seek($stream, $offset, $whence, $uri);
        }
    }

    /**
     * Get the file position indicator for a stream
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     *
     * @phpstan-impure
     */
    public static function tell($stream, $uri = null): int
    {
        $result = @ftell($stream);
        return self::maybeThrow($result, 'Error getting file position indicator for stream: %s', $uri, $stream);
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
        self::maybeThrow($result, 'Error truncating stream: %s', $uri, $stream);
    }

    /**
     * Write to an open stream
     *
     * @param resource $stream
     * @param int<0,max>|null $length
     * @param Stringable|string|null $uri
     * @throws UnwrittenDataException when fewer bytes are written than
     * expected.
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
     * Write to an open stream until there is no unwritten data
     *
     * @param resource $stream
     * @param int<0,max>|null $length
     * @param Stringable|string|null $uri
     */
    public static function writeAll($stream, string $data, ?int $length = null, $uri = null): int
    {
        if ($length !== null) {
            $data = substr($data, 0, $length);
        }
        if ($data === '') {
            return 0;
        }
        $result = 0;
        do {
            $result += self::maybeWrite($stream, $data, $data, null, $uri);
            if ($data === '') {
                return $result;
            }
            // Minimise CPU usage, e.g. when writing to non-blocking streams
            // @codeCoverageIgnoreStart
            usleep(10000);
            // @codeCoverageIgnoreEnd
        } while (true);
    }

    /**
     * Write to an open stream and apply any unwritten data to a buffer
     *
     * @param resource $stream
     * @param-out string $buffer
     * @param int<0,max>|null $length
     * @param Stringable|string|null $uri
     */
    public static function maybeWrite($stream, string $data, ?string &$buffer, ?int $length = null, $uri = null): int
    {
        $result = self::doWrite($stream, $data, $length, $unwritten, $uri);
        $buffer = substr($data, $result);
        return $result;
    }

    /**
     * @param resource $stream
     * @param int<0,max>|null $length
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
        self::maybeThrow($result, 'Error writing to stream: %s', $uri, $stream);
        assert($result <= $expected);
        $unwritten = $expected - $result;
        return $result;
    }

    /**
     * Write a line of comma-separated values to an open stream
     *
     * A shim for {@see fputcsv()} with `$eol` (added in PHP 8.1) and without
     * `$escape` (which should be removed).
     *
     * @param resource $stream
     * @param (int|float|string|bool|null)[] $fields
     * @param Stringable|string|null $uri
     */
    public static function writeCsvLine(
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
                    . str_replace($enclosure, $enclosure . $enclosure, (string) $field)
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
     * Copy a file or stream to another file or stream
     *
     * @param Stringable|string|resource $from
     * @param Stringable|string|resource $to
     * @param Stringable|string|null $fromUri
     * @param Stringable|string|null $toUri
     */
    public static function copy($from, $to, $fromUri = null, $toUri = null): void
    {
        $fromIsResource = is_resource($from);
        $toIsResource = is_resource($to);
        if (
            ($fromIsResource xor $toIsResource)
            || (Test::isStringable($from) xor Test::isStringable($to))
        ) {
            throw new InvalidArgumentException(
                'Argument #1 ($from) and argument #2 ($to) must both be Stringable|string or resource'
            );
        }

        if ($fromIsResource && $toIsResource) {
            self::assertResourceIsStream($from);
            self::assertResourceIsStream($to);
            $result = @stream_copy_to_stream($from, $to);
            self::maybeThrow(
                $result,
                'Error copying stream %s to %s',
                $fromUri,
                $from,
                self::getFriendlyStreamUri($toUri, $to),
            );
            return;
        }

        $from = (string) $from;
        $to = (string) $to;
        $result = @copy($from, $to);
        self::maybeThrow($result, 'Error copying %s to %s', $from, null, $to);
    }

    /**
     * Get the contents of a file or stream
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     */
    public static function getContents($resource, ?int $offset = null, $uri = null): string
    {
        if (is_resource($resource)) {
            self::assertResourceIsStream($resource);
            $result = @stream_get_contents($resource, -1, $offset ?? -1);
            return self::maybeThrow($result, 'Error reading stream: %s', $uri, $resource);
        }
        self::assertResourceIsStringable($resource);
        $resource = (string) $resource;
        $result = @file_get_contents($resource, false, null, $offset ?? 0);
        return self::maybeThrow($result, 'Error reading file: %s', $resource);
    }

    /**
     * Get CSV-formatted data from a file or stream
     *
     * @todo Detect file encoding
     *
     * @param Stringable|string|resource $resource
     * @return array<mixed[]>
     */
    public static function getCsv($resource): array
    {
        $handle = self::maybeOpen($resource, 'rb', $close, $uri);
        while (($row = @fgetcsv($handle, 0, ',', '"', '')) !== false) {
            /** @var array<int,string|null> $row */
            $data[] = $row;
        }
        self::checkEof($handle, $uri);
        if ($close) {
            self::close($handle, $uri);
        }
        return $data ?? [];
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
        $handle = self::maybeOpen($resource, 'r', $close, $uri);
        $line = self::readLine($handle, $uri);
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
        self::assertResourceIsStringable($resource);
        $resource = (string) $resource;
        $result = @file($resource);
        return self::maybeThrow($result, 'Error reading file: %s', $resource);
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
    public static function hasPhp($resource, $uri = null): bool
    {
        $handle = self::maybeOpen($resource, 'r', $close, $uri);
        $line = self::readLine($handle, $uri);
        if ($line !== '' && substr($line, 0, 2) === '#!') {
            $line = self::readLine($handle, $uri);
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
            return self::maybeThrow($result, 'Error getting status of stream: %s', $uri, $resource);
        }
        self::assertResourceIsStringable($resource);
        $resource = (string) $resource;
        $result = @stat($resource);
        return self::maybeThrow($result, 'Error getting file status: %s', $resource);
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
        $handle = self::maybeOpen($resource, 'wb', $close, $uri);

        if ($utf16le) {
            if (!extension_loaded('iconv')) {
                // @codeCoverageIgnoreStart
                throw new InvalidRuntimeConfigurationException(
                    "'iconv' extension required for UTF-16LE encoding"
                );
                // @codeCoverageIgnoreEnd
            }
            $filter = @stream_filter_append($handle, 'convert.iconv.UTF-8.UTF-16LE', \STREAM_FILTER_WRITE);
            self::maybeThrow($filter, 'Error applying UTF-16LE filter to stream: %s', $uri, $handle);
        }

        if ($bom) {
            self::write($handle, "\u{FEFF}", null, $uri);
        }

        $count = 0;
        foreach ($data as $entry) {
            if ($callback) {
                $entry = $callback($entry);
            }

            /** @var (int|float|string|bool|mixed[]|object|null)[] $entry */
            $row = Arr::toScalars($entry, $nullValue);

            if (!$count && $headerRow) {
                self::writeCsvLine($handle, array_keys($row), ',', '"', $eol, $uri);
            }

            self::writeCsvLine($handle, $row, ',', '"', $eol, $uri);
            $count++;
        }

        if ($close) {
            self::close($handle, $uri);
        } elseif ($utf16le) {
            $result = @stream_filter_remove($filter);
            self::maybeThrow($result, 'Error removing UTF-16LE filter from stream: %s', $uri, $handle);
        }
    }

    private static function getTempDir(): string
    {
        $tempDir = sys_get_temp_dir();
        $tmp = @realpath($tempDir);
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
     * @param mixed $resource
     * @phpstan-assert Stringable|string $resource
     */
    private static function assertResourceIsStringable($resource): void
    {
        if (!Test::isStringable($resource)) {
            throw new InvalidArgumentTypeException(1, 'resource', 'Stringable|string|resource', $resource);
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
    private static function maybeThrow(
        $result,
        string $message,
        $uri = null,
        $stream = null,
        ...$args
    ) {
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
