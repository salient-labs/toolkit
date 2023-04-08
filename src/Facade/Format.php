<?php declare(strict_types=1);

namespace Lkrms\Facade;

use DateTimeInterface;
use Lkrms\Concept\Facade;
use Lkrms\Utility\Formatters;

/**
 * A facade for \Lkrms\Utility\Formatters
 *
 * @method static Formatters load() Load and return an instance of the underlying Formatters class
 * @method static Formatters getInstance() Get the underlying Formatters instance
 * @method static bool isLoaded() True if an underlying Formatters instance has been loaded
 * @method static void unload() Clear the underlying Formatters instance
 * @method static string array(array $array, string $format = "%s: %s\n", int $indentSpaces = 4) Format an array's keys and values (see {@see Formatters::array()})
 * @method static string bool(bool $value) "true" if a boolean is true, "false" if it's not
 * @method static string bytes(int $bytes, int $precision = 0) Round an integer to an appropriate binary unit (B, KiB, MiB, TiB, ...)
 * @method static string date(DateTimeInterface $date, string $between = '[]') Format a DateTime without redundant information
 * @method static string dateRange(DateTimeInterface $from, DateTimeInterface $to, string $between = '[]', string $delimiter = '–') Format a DateTime range without redundant information
 * @method static string list(array $list, string $format = "- %s\n", int $indentSpaces = 2) Format values in a list (see {@see Formatters::list()})
 * @method static string yn(bool $value) "yes" if a boolean is true, "no" if it's not
 *
 * @uses Formatters
 *
 * @extends Facade<Formatters>
 *
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
