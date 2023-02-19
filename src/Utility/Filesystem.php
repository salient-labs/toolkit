<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Facade\Compute;
use Lkrms\Facade\Convert;
use Lkrms\Facade\File;
use Lkrms\Facade\Sys;
use Lkrms\Facade\Test;
use Phar;
use RuntimeException;

/**
 * Work with files, directories and paths
 *
 */
final class Filesystem
{
    /**
     * Get a file's end-of-line sequence
     *
     * @param string $filename
     * @return string|false `"\r\n"` or `"\n"` on success, or `false` if the
     * file's line endings couldn't be determined.
     */
    public function getEol(string $filename)
    {
        if (($handle = fopen($filename, 'r')) === false ||
                ($line = fgets($handle)) === false ||
                fclose($handle) === false) {
            return false;
        }

        foreach (["\r\n", "\n"] as $eol) {
            if (substr($line, -strlen($eol)) == $eol) {
                return $eol;
            }
        }

        return false;
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
    public function maybeCreate(string $filename, int $permissions = 0777, int $dirPermissions = 0777): bool
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
     * @param string $filename Full path to the directory.
     * @param int $permissions Only used if `$filename` needs to be created.
     * @return bool `true` on success or `false` on failure.
     */
    public function maybeCreateDirectory(string $filename, int $permissions = 0777): bool
    {
        if (is_dir($filename) || mkdir($filename, $permissions, true)) {
            return true;
        }

        return false;
    }

    /**
     * Delete a file if it exists
     *
     * @return bool `true` on success or `false` on failure.
     */
    public function maybeDelete(string $filename): bool
    {
        if (!is_file($filename)) {
            return true;
        }

        return unlink($filename);
    }

    /**
     * A Phar-friendly realpath()
     *
     * If a Phar archive is running and `$filename` is a `phar://` URL:
     * - relative path segments in `$filename` (e.g. `/../..`) are resolved by
     *   {@see Conversions::resolvePath()}
     * - if the file or directory exists, the resolved pathname is returned
     * - if `$filename` doesn't exist, `false` is returned
     *
     * Otherwise, the return value of `realpath($filename)` is returned.
     *
     * @return string|false
     */
    public function realpath(string $filename)
    {
        if (Test::isPharUrl($filename) && Phar::running()) {
            $filename = Convert::resolvePath($filename);

            return file_exists($filename) ? $filename : false;
        }

        return realpath($filename);
    }

    /**
     * Get the URI or filename associated with a stream
     *
     * @param resource $stream Any stream created by `fopen()`, `fsockopen()`,
     * `pfsockopen()` or `stream_socket_client()`.
     * @return string|null `null` if `$stream` is not an open stream resource.
     */
    public function getStreamUri($stream): ?string
    {
        if (is_resource($stream) && get_resource_type($stream) == 'stream') {
            return stream_get_meta_data($stream)['uri'];
        }

        return null;
    }

    /**
     * Convert data to CSV
     *
     * @param iterable $data A series of arrays (rows) to convert to CSV.
     * @param string|null $filename Path to the output file, or `null` to return
     * the CSV as a string.
     * @param bool $headerRow If `true`, add `array_keys($row)` before the first
     * row.
     * @param string $nullValue What should appear in cells that are `null`?
     * @return string|false|void
     * @throws RuntimeException
     */
    public function writeCsv(iterable $data, ?string $filename = null, bool $headerRow = true, ?string $nullValue = null, ?int &$count = null, ?callable $callback = null)
    {
        if (is_null($filename)) {
            $filename = 'php://temp';
            $return   = true;
        }

        if (($f = fopen($filename, 'w')) === false) {
            throw new RuntimeException("Could not open $filename");
        }

        $count = 0;
        foreach ($data as $row) {
            if ($callback) {
                $row = $callback($row);
            }

            if ($headerRow) {
                if (fputcsv($f, array_keys($row)) === false) {
                    throw new RuntimeException("Could not write to $filename");
                }
                $headerRow = false;
            }

            foreach ($row as &$value) {
                if (is_null($value)) {
                    $value = $nullValue;
                } elseif (!is_scalar($value)) {
                    $value = json_encode($value);
                }
            }

            if (fputcsv($f, $row) === false) {
                throw new RuntimeException("Could not write to $filename");
            }
            $count++;
        }

        if ($return ?? false) {
            rewind($f);
            $csv = stream_get_contents($f);
            fclose($f);

            return $csv;
        } else {
            fclose($f);
        }
    }

    /**
     * Return the name of a file unique to the current script and user
     *
     * Unlike with `tempnam()`, nothing is created on the filesystem.
     *
     * @param string $suffix
     * @param string|null $dir If null, `sys_get_temp_dir()` is used.
     * @return string
     */
    public function getStablePath(string $suffix = '.log', string $dir = null)
    {
        $program  = Sys::getProgramName();
        $basename = basename($program);
        $hash     = Compute::hash(File::realpath($program));
        $euid     = posix_geteuid();

        return (is_null($dir)
                ? realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR
                : ($dir ? rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : ''))
            . "$basename-$hash-$euid$suffix";
    }
}
