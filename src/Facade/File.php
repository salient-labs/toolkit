<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Contract\IIterable;
use Lkrms\Utility\Filesystem;

/**
 * A facade for \Lkrms\Utility\Filesystem
 *
 * @method static Filesystem load() Load and return an instance of the underlying Filesystem class
 * @method static Filesystem getInstance() Get the underlying Filesystem instance
 * @method static bool isLoaded() True if an underlying Filesystem instance has been loaded
 * @method static void unload() Clear the underlying Filesystem instance
 * @method static IIterable find(string $directory, ?string $exclude = null, ?string $include = null, ?array $excludeCallbacks = null, ?array $includeCallbacks = null, bool $recursive = true) Iterate over files in a directory (see {@see Filesystem::find()})
 * @method static string|false getEol(string $filename) Get a file's end-of-line sequence (see {@see Filesystem::getEol()})
 * @method static string getStablePath(string $suffix = '.log', ?string $dir = null) Return the name of a file unique to the current script and user (see {@see Filesystem::getStablePath()})
 * @method static string|null getStreamUri(resource $stream) Get the URI or filename associated with a stream (see {@see Filesystem::getStreamUri()})
 * @method static bool isPhp(string $filename) True if a file appears to contain PHP code
 * @method static bool maybeCreate(string $filename, int $permissions = 511, int $dirPermissions = 511) Create a file if it doesn't exist (see {@see Filesystem::maybeCreate()})
 * @method static bool maybeCreateDirectory(string $filename, int $permissions = 511) Create a directory if it doesn't exist (see {@see Filesystem::maybeCreateDirectory()})
 * @method static bool maybeDelete(string $filename) Delete a file if it exists (see {@see Filesystem::maybeDelete()})
 * @method static string|false realpath(string $filename) A Phar-friendly realpath() (see {@see Filesystem::realpath()})
 *
 * @uses Filesystem
 *
 * @extends Facade<Filesystem>
 *
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Filesystem' 'Lkrms\Facade\File'
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
     * Convert data to CSV
     *
     * @param string $nullValue
     * @return string|false|void
     * @see Filesystem::writeCsv()
     */
    public static function writeCsv(iterable $data, ?string $filename = null, bool $headerRow = true, ?string $nullValue = null, ?int &$count = null, ?callable $callback = null)
    {
        static::setFuncNumArgs(__FUNCTION__, func_num_args());
        try {
            return static::getInstance()->writeCsv($data, $filename, $headerRow, $nullValue, $count, $callback);
        } finally {
            static::clearFuncNumArgs(__FUNCTION__);
        }
    }
}
