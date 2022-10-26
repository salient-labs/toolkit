<?php

declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Facade\Convert;
use SQLite3;

/**
 * Get information about the runtime environment
 *
 */
final class System
{
    public function getMemoryLimit(): int
    {
        return Convert::sizeToBytes(ini_get("memory_limit") ?: "0");
    }

    public function getMemoryUsage(): int
    {
        return memory_get_usage();
    }

    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage();
    }

    /**
     * Get user and system CPU times for the current run, in microseconds
     *
     * @return array{0:int,1:int}
     * ```php
     * [$userMicroseconds, $systemMicroseconds]
     * ```
     */
    public function getCpuUsage(): array
    {
        if (($usage = getrusage()) === false)
        {
            return [
                0,
                0,
            ];
        }

        return [
            ($usage["ru_utime.tv_sec"] ?? 0) * 1000000 + ($usage["ru_utime.tv_usec"] ?? 0),
            ($usage["ru_stime.tv_sec"] ?? 0) * 1000000 + ($usage["ru_stime.tv_usec"] ?? 0),
        ];
    }

    public function getMemoryUsagePercent(): int
    {
        $limit = $this->getMemoryLimit();
        if ($limit <= 0)
        {
            return 0;
        }
        else
        {
            return (int)round(memory_get_usage(true) * 100 / $limit);
        }
    }

    public function getProgramName(?string $relativeTo = null): string
    {
        if (!is_null($relativeTo) &&
            ($relativeTo = realpath($relativeTo)) !== false &&
            ($scriptPath = realpath($_SERVER["SCRIPT_FILENAME"])) !== false &&
            strpos($scriptPath, $relativeTo) === 0)
        {
            return substr($scriptPath, strlen($relativeTo) + 1);
        }

        return $_SERVER["SCRIPT_FILENAME"];
    }

    /**
     * @param int $extLimit If set, remove up to `$extLimit` extensions from the
     * program's basename as per {@see Conversions::pathToBasename()}.
     */
    public function getProgramBasename(int $extLimit = 0): string
    {
        return Convert::pathToBasename($_SERVER["SCRIPT_FILENAME"], $extLimit);
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
        return SQLite3::version()["versionNumber"];
    }

}
