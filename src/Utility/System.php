<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Facade\Convert;
use RuntimeException;
use SQLite3;

/**
 * Get information about the runtime environment
 *
 */
final class System
{
    /**
     * Get the configured memory_limit in bytes
     *
     */
    public function getMemoryLimit(): int
    {
        return Convert::sizeToBytes(ini_get('memory_limit') ?: '0');
    }

    /**
     * Get the current memory usage of the script in bytes
     *
     */
    public function getMemoryUsage(): int
    {
        return memory_get_usage();
    }

    /**
     * Get the peak memory usage of the script in bytes
     *
     */
    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage();
    }

    /**
     * Get the current memory usage of the script as a percentage of the
     * memory_limit
     *
     */
    public function getMemoryUsagePercent(): int
    {
        $limit = $this->getMemoryLimit();

        return $limit <= 0
            ? 0
            : (int) round(memory_get_usage() * 100 / $limit);
    }

    /**
     * Get user and system CPU times for the current run, in microseconds
     *
     * @return int[]
     * ```php
     * [$userMicroseconds, $systemMicroseconds]
     * ```
     */
    public function getCpuUsage(): array
    {
        if (($usage = getrusage()) === false) {
            return [
                0,
                0,
            ];
        }

        return [
            ($usage['ru_utime.tv_sec'] ?? 0) * 1000000 + ($usage['ru_utime.tv_usec'] ?? 0),
            ($usage['ru_stime.tv_sec'] ?? 0) * 1000000 + ($usage['ru_stime.tv_usec'] ?? 0),
        ];
    }

    /**
     * Get the filename used to run the script
     *
     * @param string|null $basePath If set, `"{$basePath}/"` is removed from the
     * beginning of the filename, and if the filename does not start with
     * `$basePath`, a `RuntimeException` is thrown.
     */
    public function getProgramName(?string $basePath = null): string
    {
        $filename = $_SERVER['SCRIPT_FILENAME'];
        if (is_null($basePath)) {
            return $filename;
        }
        if (($basePath = realpath($basePath)) !== false &&
                ($filename = realpath($filename)) !== false &&
                strpos($filename, $basePath) === 0) {
            return substr($filename, strlen($basePath) + 1);
        }

        throw new RuntimeException('SCRIPT_FILENAME is not in $basePath');
    }

    /**
     * Return the basename of the file used to run the script
     *
     * @param string $suffix Removed from the end of the filename if set.
     */
    public function getProgramBasename(string $suffix = ''): string
    {
        return basename($_SERVER['SCRIPT_FILENAME'], $suffix);
    }

    /**
     * Return true if the SQLite3 library supports UPSERT syntax
     *
     * @link https://www.sqlite.org/lang_UPSERT.html
     */
    public function sqliteHasUpsert(): bool
    {
        return $this->getSQLite3Version() >= 3024000;
    }

    private function getSQLite3Version(): int
    {
        return SQLite3::version()['versionNumber'];
    }
}
