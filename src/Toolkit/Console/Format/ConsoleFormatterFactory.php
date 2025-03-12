<?php declare(strict_types=1);

namespace Salient\Console\Format;

use Salient\Contract\Console\Format\ConsoleFormatterInterface as FormatterInterface;

interface ConsoleFormatterFactory
{
    /**
     * Get a console output formatter
     */
    public static function getFormatter(): FormatterInterface;
}
