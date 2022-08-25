<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Utility\Filesystem;

/**
 * A facade for Filesystem
 *
 * @method static string|null getClassPath(string $class)
 * @method static string|false getEol(string $filename) Get a file's end-of-line sequence
 * @method static string|null getNamespacePath(string $namespace)
 * @method static string getStablePath(string $suffix = '.log', ?string $dir = null) Return the name of a file unique to the current script and user
 * @method static string|null getStreamUri(resource $stream) Get the URI or filename associated with a stream
 * @method static bool maybeCreate(string $filename, int $permissions = 511, int $dirPermissions = 511) Create a file if it doesn't exist
 * @method static bool maybeCreateDirectory(string $filename, int $permissions = 511) Create a directory if it doesn't exist
 * @method static string|false|void writeCsv(array $data, ?string $filename = null, bool $headerRow = true, string $nullValue = null) Convert an array to CSV
 *
 * @uses Filesystem
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Filesystem' --generate='Lkrms\Facade\File'
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
}
