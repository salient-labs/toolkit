<?php

declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Facade\Convert;

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

    public function getProgramName(): string
    {
        return $_SERVER["SCRIPT_FILENAME"];
    }
}
