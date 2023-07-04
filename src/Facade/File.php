<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Support\Iterator\Contract\FluentIteratorInterface;
use Lkrms\Utility\Filesystem;
use SplFileInfo;

/**
 * A facade for \Lkrms\Utility\Filesystem
 *
 * @method static Filesystem load() Load and return an instance of the underlying Filesystem class
 * @method static Filesystem getInstance() Get the underlying Filesystem instance
 * @method static bool isLoaded() True if an underlying Filesystem instance has been loaded
 * @method static void unload() Clear the underlying Filesystem instance
 * @method static string createTemporaryDirectory() Create a temporary directory
 * @method static FluentIteratorInterface<string,SplFileInfo> find(string $directory, string|null $exclude = null, string|null $include = null, array<string,callable(SplFileInfo): bool> $excludeCallbacks = null, array<string,callable(SplFileInfo): bool> $includeCallbacks = null, bool $recursive = true, bool $withDirectories = false, bool $withDirectoriesFirst = true) Iterate over files in a directory (see {@see Filesystem::find()})
 * @method static string|false getEol(string $filename) Get a file's end-of-line sequence (see {@see Filesystem::getEol()})
 * @method static string getStablePath(string $suffix = '.log', string|null $dir = null) Return the name of a file unique to the current script and user (see {@see Filesystem::getStablePath()})
 * @method static string|null getStreamUri(resource $stream) Get the URI or filename associated with a stream (see {@see Filesystem::getStreamUri()})
 * @method static bool isPhp(string $filename) True if a file appears to contain PHP code
 * @method static bool maybeCreate(string $filename, int $permissions = 511, int $dirPermissions = 511) Create a file if it doesn't exist (see {@see Filesystem::maybeCreate()})
 * @method static bool maybeCreateDirectory(string $directory, int $permissions = 511) Create a directory if it doesn't exist (see {@see Filesystem::maybeCreateDirectory()})
 * @method static bool maybeDelete(string $filename) Delete a file if it exists (see {@see Filesystem::maybeDelete()})
 * @method static bool maybeDeleteDirectory(string $directory, bool $recursive = false) Delete a directory if it exists (see {@see Filesystem::maybeDeleteDirectory()})
 * @method static bool pruneDirectory(string $directory) Recursively delete the contents of a directory without deleting the directory itself (see {@see Filesystem::pruneDirectory()})
 * @method static string|false realpath(string $filename) A Phar-friendly, file descriptor-aware realpath() (see {@see Filesystem::realpath()})
 *
 * @uses Filesystem
 *
 * @extends Facade<Filesystem>
 */
final class File extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Filesystem::class;
    }

    /**
     * Write data to a CSV file or stream
     *
     * @param iterable<mixed[]> $data
     * @param string|resource|null $target
     * @param int|string|null $nullValue
     * @param callable(mixed): mixed[] $callback
     * @param int|null $count
     * @return string|true
     * @see Filesystem::writeCsv()
     */
    public static function writeCsv(iterable $data, $target = null, bool $headerRow = true, $nullValue = null, ?callable $callback = null, ?int &$count = null, string $eol = "\r\n", bool $utf16le = true, bool $bom = true)
    {
        static::setFuncNumArgs(__FUNCTION__, func_num_args());
        try {
            return static::getInstance()->writeCsv($data, $target, $headerRow, $nullValue, $callback, $count, $eol, $utf16le, $bom);
        } finally {
            static::clearFuncNumArgs(__FUNCTION__);
        }
    }
}
