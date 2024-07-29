<?php declare(strict_types=1);

namespace Salient\Console\Contract;

use Salient\Contract\Console\ConsoleFormatterInterface as FormatterInterface;

interface ConsoleFormatterFactory
{
    /**
     * Get a console output formatter
     */
    public static function getFormatter(): FormatterInterface;
}
