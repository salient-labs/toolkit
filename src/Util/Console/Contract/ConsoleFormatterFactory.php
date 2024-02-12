<?php declare(strict_types=1);

namespace Lkrms\Console\Contract;

use Lkrms\Console\ConsoleFormatter as Formatter;

interface ConsoleFormatterFactory
{
    /**
     * Get a console output formatter
     */
    public static function getFormatter(): Formatter;
}
