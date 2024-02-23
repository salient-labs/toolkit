<?php declare(strict_types=1);

namespace Salient\Console\Contract;

use Salient\Console\ConsoleFormatter as Formatter;

interface ConsoleFormatterFactory
{
    /**
     * Get a console output formatter
     */
    public static function getFormatter(): Formatter;
}
