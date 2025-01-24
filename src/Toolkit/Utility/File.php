<?php declare(strict_types=1);

namespace Salient\Utility;

use Salient\Core\Process;
use Salient\Iterator\RecursiveFilesystemIterator;
use Salient\Utility\Exception\FilesystemErrorException;
use Salient\Utility\Exception\InvalidRuntimeConfigurationException;
use Salient\Utility\Exception\UnreadDataException;
use Salient\Utility\Exception\UnwrittenDataException;
use InvalidArgumentException;
use Stringable;

/**
 * Work with files, streams and paths
 *
 * Methods with an optional `$uri` parameter allow the default resource URI
 * reported on failure to be overridden.
 *
 * @api
 */
final class File extends AbstractUtility
{
    /**
     * Check if a path is absolute without accessing the filesystem
     *
     * Returns `true` if `$path` starts with `/`, `\\`, `<letter>:\`,
     * `<letter>:/` or a URI scheme with two or more characters.
     */
    public static function isAbsolute(string $path): bool
    {
        return (bool) Regex::match(
            '@^(?:/|\\\\\\\\|[a-z]:[/\\\\]|[a-z][-a-z0-9+.]+:)@i',
            $path,
        );
    }

    /**
     * Resolve "/./" and "/../" segments in a path without accessing the
     * filesystem
     *
     * If `$withUriSegments` is `true`, `"/../"` segments after empty segments
     * (e.g. `"/dir1/dir2//../"`) are resolved by removing an empty segment,
     * otherwise consecutive directory separators are treated as one separator.
     */
    public static function resolvePath(string $path, bool $withUriSegments = false): string
    {
        $path = str_replace('\\', '/', $path);

        // Remove "/./" segments
        $path = Regex::replace('@(?<=/|^)\.(?:/|$)@', '', $path);

        // Remove "/../" segments
        $regex = $withUriSegments ? '/' : '/+';
        $regex = "@(?:^|(?<=^/)|(?<=/|^(?!/))(?!\.\.(?:/|\$))[^/]*{$regex})\.\.(?:/|\$)@";
        do {
            $path = Regex::replace($regex, '', $path, -1, $count);
        } while ($count);

        return $path;
    }

    /**
     * Sanitise the path to a directory without accessing the filesystem
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
        self::check(@chdir($directory), 'chdir', $directory);
    }

    /**
     * Create a directory
     */
    public static function mkdir(
        string $directory,
        int $permissions = 0777,
        bool $recursive = false
    ): void {
        self::check(@mkdir($directory, $permissions, $recursive), 'mkdir', $directory);
    }

    /**
     * Change file permissions
     */
    public static function chmod(string $filename, int $permissions): void
    {
        self::check(@chmod($filename, $permissions), 'chmod', $filename);
    }

    /**
     * Rename a file or directory
     */
    public static function rename(string $from, string $to): void
    {
        self::check(@rename($from, $to), 'rename', null, null, $from, $to);
    }

    /**
     * Create a symbolic link to a target with a given name
     */
    public static function symlink(string $target, string $link): void
    {
        self::check(@symlink($target, $link), 'symlink', null, null, $target, $link);
    }

    /**
     * Set file access and modification times
     */
    public static function touch(
        string $filename,
        ?int $mtime = null,
        ?int $atime = null
    ): void {
        $mtime ??= time();
        $atime ??= $mtime;
        self::check(@touch($filename, $mtime, $atime), 'touch', $filename);
    }

    /**
     * Get a path or its closest parent that exists
     *
     * Returns `null` if the leftmost segment of `$path` doesn't exist, or if
     * the closest parent that exists is not a directory.
     */
    public static function getClosestPath(string $path): ?string
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
     * Safely create a file if it doesn't exist
     *
     * @param int $permissions Applied if `$filename` is created.
     * @param int $dirPermissions Applied if `$filename`'s directory is created.
     * Parent directories, if any, are created with file mode `0777 & ~umask()`,
     * or `0755` if `$umaskApplies` is `false`.
     */
    public static function create(
        string $filename,
        int $permissions = 0777,
        int $dirPermissions = 0777,
        bool $umaskApplies = true
    ): void {
        if (is_file($filename)) {
            return;
        }
        self::createDir(dirname($filename), $dirPermissions, $umaskApplies);
        $umask = umask();
        if ($umaskApplies) {
            $permissions &= ~$umask;
        }
        try {
            // Create the file without group or other permissions to prevent
            // access by a bad actor before permissions are set
            umask(077);
            $handle = self::open($filename, 'x');
            self::chmod($filename, $permissions);
            self::close($handle, $filename);
        } finally {
            umask($umask);
        }
    }

    /**
     * Safely create a directory if it doesn't exist
     *
     * @param int $permissions Applied if `$directory` is created. Parent
     * directories, if any, are created with file mode `0777 & ~umask()`, or
     * `0755` if `$umaskApplies` is `false`.
     */
    public static function createDir(
        string $directory,
        int $permissions = 0777,
        bool $umaskApplies = true
    ): void {
        if (is_dir($directory)) {
            return;
        }
        $parent = dirname($directory);
        $umask = umask();
        if ($umaskApplies) {
            $permissions &= ~$umask;
        }
        try {
            if (!is_dir($parent)) {
                umask(0);
                $parentPerms = $umaskApplies
                    ? 0777 & ~$umask
                    : 0755;
                self::mkdir($parent, $parentPerms, true);
            }
            umask(077);
            self::mkdir($directory);
            self::chmod($directory, $permissions);
        } finally {
            umask($umask);
        }
    }

    /**
     * Create a temporary file with mode 0600
     *
     * @param string|null $directory If `null`, the directory returned by
     * `sys_get_temp_dir()` is used.
     * @param string|null $prefix If `null`, the basename of the file used to
     * run the script is used.
     */
    public static function createTemp(
        ?string $directory = null,
        ?string $prefix = null
    ): string {
        if ($directory !== null) {
            $directory = self::sanitiseDir($directory);
            if (!is_dir($directory) || !is_writable($directory)) {
                throw new FilesystemErrorException(sprintf(
                    'Not a writable directory: %s',
                    $directory,
                ));
            }
        }
        return self::check(@tempnam(
            $directory ?? Sys::getTempDir(),
            $prefix ?? Sys::getProgramBasename(),
        ), 'tempnam');
    }

    /**
     * Create a temporary directory with file mode 0700
     *
     * @param string|null $directory If `null`, the directory returned by
     * `sys_get_temp_dir()` is used.
     * @param string|null $prefix If `null`, the basename of the file used to
     * run the script is used.
     */
    public static function createTempDir(
        ?string $directory = null,
        ?string $prefix = null
    ): string {
        if ($directory !== null) {
            $directory = self::sanitiseDir($directory);
        }
        $directory ??= Sys::getTempDir();
        $prefix ??= Sys::getProgramBasename();
        $failed = false;
        do {
            if ($failed) {
                clearstatcache();
                if (!is_dir($directory) || !is_writable($directory)) {
                    throw new FilesystemErrorException(sprintf(
                        'Not a writable directory: %s',
                        $directory,
                    ));
                }
                usleep(10000);
            }
            $dir = sprintf(
                '%s/%s%s.tmp',
                $directory,
                $prefix,
                Get::randomText(8),
            );
            $failed = !@mkdir($dir, 0700);
        } while ($failed);
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
        self::check(@unlink($filename), 'unlink', $filename);
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
        self::check(@rmdir($directory), 'rmdir', $directory);
    }

    /**
     * Iterate over files in one or more directories
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
        $command = Sys::isWindows() ? 'cd' : 'pwd';
        if (class_exists(Process::class)) {
            $process = Process::withShellCommand($command);
            if ($process->run() === 0) {
                return $process->getText();
            }
        } else {
            // @codeCoverageIgnoreStart
            $pipe = self::openPipe($command, 'r');
            $dir = self::getContents($pipe);
            if (self::closePipe($pipe, $command) === 0) {
                return Str::trimNativeEol($dir);
            }
            // @codeCoverageIgnoreEnd
        }
        error_clear_last();
        return self::check(@getcwd(), 'getcwd');
    }

    /**
     * Check if a path exists and is writable, or doesn't exist but descends
     * from a writable directory
     */
    public static function isCreatable(string $path): bool
    {
        $path = self::getClosestPath($path);
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

        if ($setPermissions) {
            clearstatcache();
            // With exceptions `chmod()` can't address:
            // - On *nix, filesystem entries can be deleted if their parent
            //   directory is writable
            // - On Windows, they can be deleted if they are writable, whether
            //   their parent directory is writable or not
            if (!Sys::isWindows()) {
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
                $setPermissions = false;
            }
        }

        foreach ($files->dirsLast() as $file) {
            $filename = (string) $file;
            if ($setPermissions && !$file->isWritable()) {
                // This will only ever run on Windows
                self::chmod($filename, $file->isDir() ? 0700 : 0600);
            }
            if ($file->isDir()) {
                self::check(@rmdir($filename), 'rmdir', $filename);
            } else {
                self::check(@unlink($filename), 'unlink', $filename);
            }
        }

        if ($delete) {
            self::check(@rmdir($directory), 'rmdir', $directory);
        }
    }

    /**
     * Resolve symbolic links and relative references in a path or Phar URI
     *
     * An exception is thrown if `$path` does not exist.
     */
    public static function realpath(string $path): string
    {
        if (Str::lower(substr($path, 0, 7)) === 'phar://' && file_exists($path)) {
            return self::resolvePath($path, true);
        }
        error_clear_last();
        return self::check(@realpath($path), 'realpath', $path);
    }

    /**
     * Get a path relative to a parent directory
     *
     * Returns `$default` if `$path` does not belong to `$parentDirectory`.
     *
     * An exception is thrown if `$path` or `$parentDirectory` do not exist.
     *
     * @template TDefault of string|null
     *
     * @param TDefault $default
     * @return string|TDefault
     */
    public static function getRelativePath(
        string $path,
        string $parentDirectory,
        ?string $default = null
    ): ?string {
        $path = self::realpath($path);
        $basePath = self::realpath($parentDirectory);
        if (strpos($path, $basePath) === 0) {
            return substr($path, strlen($basePath) + 1);
        }
        return $default;
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
        return $stat1['dev'] === $stat2['dev']
            && $stat1['ino'] === $stat2['ino'];
    }

    /**
     * Get a unique identifier for a file from its device and inode numbers
     */
    public static function getIdentifier(string $filename): string
    {
        $stat = self::stat($filename);
        return sprintf('%d:%d', $stat['dev'], $stat['ino']);
    }

    /**
     * Get the size of a file
     */
    public static function size(string $filename): int
    {
        return self::check(@filesize($filename), 'filesize', $filename);
    }

    /**
     * Get the type of a file
     *
     * @return ("fifo"|"char"|"dir"|"block"|"link"|"file"|"socket"|"unknown")
     */
    public static function type(string $filename): string
    {
        /** @var ("fifo"|"char"|"dir"|"block"|"link"|"file"|"socket"|"unknown") */
        return self::check(@filetype($filename), 'filetype', $filename);
    }

    /**
     * Write data to a file
     *
     * @param resource|array<int|float|string|bool|Stringable|null>|string $data
     * @param int-mask-of<\FILE_USE_INCLUDE_PATH|\FILE_APPEND|\LOCK_EX> $flags
     */
    public static function writeContents(string $filename, $data, int $flags = 0): int
    {
        return self::check(@file_put_contents($filename, $data, $flags), 'file_put_contents', $filename);
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
            self::getStreamName($uri, $stream),
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
        $uri = self::getStreamName($uri, $stream);
        self::check(@fclose($stream), 'fclose', $uri);
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
            self::check(false, 'pclose', $command ?? '<pipe>');
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
            return stream_get_meta_data($stream)['uri'] ?? null;
        }
        return null;
    }

    /**
     * Check if a value is a seekable stream resource
     *
     * @param mixed $value
     * @phpstan-assert-if-true =resource $value
     */
    public static function isSeekableStream($value): bool
    {
        return self::isStream($value)
            // @phpstan-ignore nullCoalesce.offset
            && (stream_get_meta_data($value)['seekable'] ?? false);
    }

    /**
     * Check if a value is a stream resource
     *
     * @param mixed $value
     * @phpstan-assert-if-true =resource $value
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
        return self::check(@fopen($filename, $mode), 'fopen', $filename);
    }

    /**
     * Open a resource if it is not already open
     *
     * @template TUri of Stringable|string|null
     *
     * @param Stringable|string|resource $resource
     * @param TUri $uri
     * @param-out bool $close
     * @param-out ($resource is resource ? (TUri is null ? string|null : TUri) : string) $uri
     * @return resource
     */
    public static function maybeOpen($resource, string $mode, ?bool &$close, &$uri)
    {
        $close = false;
        if (is_resource($resource)) {
            if ($uri === null) {
                $uri = self::getStreamUri($resource);
            }
            return $resource;
        }
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
        return self::check(@popen($command, $mode), 'popen', $command);
    }

    /**
     * Read from an open stream
     *
     * @param resource $stream
     * @param int<1,max> $length
     * @param Stringable|string|null $uri
     */
    public static function read($stream, int $length, $uri = null): string
    {
        return self::check(@fread($stream, $length), 'fread', $uri, $stream);
    }

    /**
     * Read from an open stream until data of the expected length is read
     *
     * @param resource $stream
     * @param int<0,max> $length
     * @param Stringable|string|null $uri
     * @throws UnreadDataException if fewer bytes are read than expected and the
     * stream is at end-of-file.
     */
    public static function readAll($stream, int $length, $uri = null): string
    {
        if ($length === 0) {
            return '';
        }
        $data = '';
        $dataLength = 0;
        do {
            /** @var int<1,max> */
            $unread = $length - $dataLength;
            $result = self::read($stream, $unread, $uri);
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
            $unread,
            'Error reading from stream: expected {{#}} more {{#:byte}} from %s',
            self::getStreamName($uri, $stream),
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
        self::check(rewind($stream), 'rewind', $uri, $stream);
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
        /** @disregard P1006 */
        if (@fseek($stream, $offset, $whence) === -1) {
            self::check(false, 'fseek', $uri, $stream);
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
            /** @disregard P1006 */
            self::seek($stream, $offset, $whence, $uri);
        }
    }

    /**
     * Get the file position indicator for a stream
     *
     * @param resource $stream
     * @param Stringable|string|null $uri
     */
    public static function tell($stream, $uri = null): int
    {
        return self::check(@ftell($stream), 'ftell', $uri, $stream);
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
        self::check(@ftruncate($stream, $size), 'ftruncate', $uri, $stream);
    }

    /**
     * Write to an open stream
     *
     * @param resource $stream
     * @param int<0,max>|null $length
     * @param Stringable|string|null $uri
     * @throws UnwrittenDataException if fewer bytes are written than expected.
     */
    public static function write($stream, string $data, ?int $length = null, $uri = null): int
    {
        $result = self::doWrite($stream, $data, $length, $unwritten, $uri);
        if ($unwritten > 0) {
            throw new UnwrittenDataException(Inflect::format(
                $unwritten,
                'Error writing to stream: {{#}} {{#:byte}} not written to %s',
                self::getStreamName($uri, $stream),
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
     * @param int<0,max>|null $length
     * @param Stringable|string|null $uri
     * @param-out string $buffer
     */
    public static function maybeWrite($stream, string $data, ?string &$buffer, ?int $length = null, $uri = null): int
    {
        $result = self::doWrite($stream, $data, $length, $unwritten, $uri);
        $buffer = (string) substr($data, $result);
        return $result;
    }

    /**
     * @param resource $stream
     * @param int<0,max>|null $length
     * @param Stringable|string|null $uri
     * @param-out int $unwritten
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
        self::check($result, 'fwrite', $uri, $stream);
        /** @var int<0,max> */
        $unwritten = $expected - $result;
        return $result;
    }

    /**
     * Write a line of comma-separated values to an open stream
     *
     * A shim for {@see fputcsv()} with `$eol` (added in PHP 8.1) and without
     * `$escape` (which should never have been added).
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
        $line = implode($separator, $fields) . $eol;
        return self::write($stream, $line, null, $uri);
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
        if (is_resource($from) && is_resource($to)) {
            $result = @stream_copy_to_stream($from, $to);
            $from = self::getStreamName($fromUri, $from);
            $to = self::getStreamName($toUri, $to);
            self::check($result, 'stream_copy_to_stream', null, null, $from, $to);
        } elseif (Test::isStringable($from) && Test::isStringable($to)) {
            $from = (string) $from;
            $to = (string) $to;
            self::check(@copy($from, $to), 'copy', null, null, $from, $to);
        } else {
            throw new InvalidArgumentException('$from and $to must both be Stringable|string or resource');
        }
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
            return self::check(@stream_get_contents($resource, -1, $offset ?? -1), 'stream_get_contents', $uri, $resource);
        }
        $resource = (string) $resource;
        return self::check(@file_get_contents($resource, false, null, $offset ?? 0), 'file_get_contents', $resource);
    }

    /**
     * Get CSV-formatted data from a file or stream
     *
     * @param Stringable|string|resource $resource
     * @param Stringable|string|null $uri
     * @return list<array{null}|list<string>>
     */
    public static function getCsv($resource, $uri = null): array
    {
        $handle = self::maybeOpen($resource, 'rb', $close, $uri);
        while (($row = @fgetcsv($handle, 0, ',', '"', '')) !== false) {
            /** @var array{null}|list<string> $row */
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
     * @return non-empty-string|null `null` if there are no recognised newline
     * characters in the file.
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
            while (($line = @fgets($resource)) !== false) {
                $lines[] = $line;
            }
            self::checkEof($resource, $uri);
            return $lines ?? [];
        }
        $resource = (string) $resource;
        return self::check(@file($resource), 'file', $resource);
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
        return (bool) Regex::match('/^<\?(php\s|(?!php|xml\s))/', $line);
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
            return self::check(@fstat($resource), 'fstat', $uri, $resource);
        }
        $resource = (string) $resource;
        return self::check(@stat($resource), 'stat', $resource);
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
     * of data?
     * @param int|float|string|bool|null $null Replace `null` values in `$data`.
     * @param (callable(TValue, int $index): mixed[])|null $callback Apply a
     * callback to each entry before it is written.
     * @param int|null $count Receives the number of entries written.
     * @param Stringable|string|null $uri
     * @param-out int $count
     */
    public static function writeCsv(
        $resource,
        iterable $data,
        bool $headerRow = true,
        $null = null,
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
            $result = @stream_filter_append($handle, 'convert.iconv.UTF-8.UTF-16LE', \STREAM_FILTER_WRITE);
            $filter = self::check($result, 'stream_filter_append', $uri, $handle);
        }
        if ($bom) {
            self::write($handle, "\u{FEFF}", null, $uri);
        }
        $count = 0;
        foreach ($data as $entry) {
            if ($callback) {
                $entry = $callback($entry, $count);
            }
            /** @var (int|float|string|bool|mixed[]|object|null)[] $entry */
            $row = Arr::toScalars($entry, $null);
            if (!$count && $headerRow) {
                self::writeCsvLine($handle, array_keys($row), ',', '"', $eol, $uri);
            }
            self::writeCsvLine($handle, $row, ',', '"', $eol, $uri);
            $count++;
        }
        if ($close) {
            self::close($handle, $uri);
        } elseif ($utf16le) {
            self::check(@stream_filter_remove($filter), 'stream_filter_remove', $uri, $handle);
        }
    }

    /**
     * @template T
     *
     * @param T|false $result
     * @param Stringable|string|null $uri
     * @param resource|null $stream
     * @return ($result is false ? never : T)
     */
    private static function check($result, string $function, $uri = null, $stream = null, string ...$args)
    {
        if ($result !== false) {
            return $result;
        }
        $error = error_get_last();
        if ($error) {
            throw new FilesystemErrorException($error['message']);
        }
        if (func_num_args() < 3) {
            throw new FilesystemErrorException(sprintf(
                'Error calling %s()',
                $function,
            ));
        }
        throw new FilesystemErrorException(sprintf(
            'Error calling %s() with %s',
            $function,
            $args
                ? implode(', ', $args)
                : self::getStreamName($uri, $stream),
        ));
    }

    /**
     * @param Stringable|string|null $uri
     * @param resource|null $stream
     */
    private static function getStreamName($uri, $stream): string
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
