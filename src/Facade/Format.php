<?php declare(strict_types=1);

namespace Lkrms\Facade;

use DateTimeInterface;
use Lkrms\Concept\Facade;
use Lkrms\Utility\Formatters;

/**
 * A facade for \Lkrms\Utility\Formatters
 *
 * @method static Formatters load() Load and return an instance of the underlying Formatters class
 * @method static Formatters getInstance() Return the underlying Formatters instance
 * @method static bool isLoaded() Return true if an underlying Formatters instance has been loaded
 * @method static void unload() Clear the underlying Formatters instance
 * @method static string array(array $array, string $format = "%s: %s\n", int $indentSpaces = 4) Format an array's keys and values (see {@see Formatters::array()})
 * @method static string bool(bool $value) Return "true" if a boolean is true, "false" if it's not (see {@see Formatters::bool()})
 * @method static mixed bytes(int $bytes, int $precision = 0) See {@see Formatters::bytes()}
 * @method static string date(DateTimeInterface $date, string $between = '[]') See {@see Formatters::date()}
 * @method static string dateRange(DateTimeInterface $from, DateTimeInterface $to, string $between = '[]', string $delimiter = '–') See {@see Formatters::dateRange()}
 * @method static string yn(bool $value) Return "yes" if a boolean is true, "no" if it's not (see {@see Formatters::yn()})
 *
 * @uses Formatters
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Formatters' 'Lkrms\Facade\Format'
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
