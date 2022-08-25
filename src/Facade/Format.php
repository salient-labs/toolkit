<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use DateTime;
use Lkrms\Concept\Facade;
use Lkrms\Utility\Formatters;

/**
 * A facade for Formatters
 *
 * @method static string array(array $array, string $format = "%s: %s\n", int $indentSpaces = 4) Format an array's keys and values
 * @method static string bool(bool $value) Return "true" if a boolean is true, "false" if it's not
 * @method static string date(DateTime $date, string $between = '[]')
 * @method static string dateRange(DateTime $from, DateTime $to, string $between = '[]', string $delimiter = '–')
 * @method static string yn(bool $value) Return "yes" if a boolean is true, "no" if it's not
 *
 * @uses Formatters
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Formatters' --generate='Lkrms\Facade\Format'
 */
final class Format extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Formatters::class;
    }
}
