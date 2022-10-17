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
        return memory_get_usage(true);
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
