<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Lkrms\Concept\Facade;
use Lkrms\Support\DateFormatter;
use Lkrms\Utility\Conversions;

/**
 * A facade for Conversions
 *
 * @method static Conversions load() Load and return an instance of the underlying `Conversions` class
 * @method static Conversions getInstance() Return the underlying `Conversions` instance
 * @method static bool isLoaded() Return true if an underlying `Conversions` instance has been loaded
 * @method static void unload() Clear the underlying `Conversions` instance
 * @method static array arrayValuesToChildArray(array $array, string $key, array $map, bool $merge = true) Move array values to a nested array (see {@see Conversions::arrayValuesToChildArray()})
 * @method static string classToBasename(string $class, ?string $suffix = null) Remove the namespace and an optional suffix from a class name (see {@see Conversions::classToBasename()})
 * @method static string classToNamespace(string $class) Return the namespace of a class (see {@see Conversions::classToNamespace()})
 * @method static string dataToQuery(array $data, bool $forceNumericKeys = false, ?DateFormatter $dateFormatter = null) A more API-friendly http_build_query (see {@see Conversions::dataToQuery()})
 * @method static mixed emptyToNull(mixed $value) If a value is 'falsey', make it null (see {@see Conversions::emptyToNull()})
 * @method static int intervalToSeconds(DateInterval|string $value) Convert an interval to the equivalent number of seconds (see {@see Conversions::intervalToSeconds()})
 * @method static array iterableToArray(iterable $iterable) If an iterable isn't already an array, make it one (see {@see Conversions::iterableToArray()})
 * @method static string linesToLists(string $text, string $separator = "\n", ?string $marker = null, string $regex = '/^\\h*[-*] /') Remove duplicates in a string where 'top-level' lines ("section names") are grouped with any subsequent 'child' lines ("list items") (see {@see Conversions::linesToLists()})
 * @method static array listToMap(array $list, string|Closure $key) Create a map from a list (see {@see Conversions::listToMap()})
 * @method static string mergeLists(string $text, string $regex = '/^\\h*[-*] /') See {@see Conversions::mergeLists()}
 * @method static string methodToFunction(string $method) Remove the class from a method name (see {@see Conversions::methodToFunction()})
 * @method static string nounToPlural(string $noun) Return the plural of a singular noun (see {@see Conversions::nounToPlural()})
 * @method static string numberToNoun(int $number, string $singular, ?string $plural = null, bool $includeNumber = false) If a number is 1, return $singular, otherwise return $plural (see {@see Conversions::numberToNoun()})
 * @method static array objectToArray(object $object) A wrapper for get_object_vars (see {@see Conversions::objectToArray()})
 * @method static string pathToBasename(string $path, int $extLimit = 0) Remove the directory and up to the given number of extensions from a path (see {@see Conversions::pathToBasename()})
 * @method static string|false scalarToString(mixed $value) Convert a scalar to a string (see {@see Conversions::scalarToString()})
 * @method static int sizeToBytes(string $size) Convert php.ini values like "128M" to bytes (see {@see Conversions::sizeToBytes()})
 * @method static string sparseToString(string $separator, array $array) Remove zero-width values from an array before imploding it (see {@see Conversions::sparseToString()})
 * @method static array toArray(mixed $value, bool $emptyIfNull = false) If a value isn't an array, make it the first element of one (see {@see Conversions::toArray()})
 * @method static string toCamelCase(string $text) Convert an identifier to camelCase (see {@see Conversions::toCamelCase()})
 * @method static string toCase(string $text, int $case = self::IDENTIFIER_CASE_SNAKE) Perform the given case conversion (see {@see Conversions::toCase()})
 * @method static DateTimeImmutable toDateTimeImmutable(DateTimeInterface $date) A shim for DateTimeImmutable::createFromInterface() (PHP 8+) (see {@see Conversions::toDateTimeImmutable()})
 * @method static string toKebabCase(string $text) Convert an identifier to kebab-case (see {@see Conversions::toKebabCase()})
 * @method static array toList(mixed $value, bool $emptyIfNull = false) If a value isn't a list, make it the first element of one (see {@see Conversions::toList()})
 * @method static array toNestedArrays(array $array, array $maps, bool $merge = true) Apply multiple arrayValuesToChildArray transformations to an array (see {@see Conversions::toNestedArrays()})
 * @method static string toNormal(string $text) Clean up a string for comparison with other strings (see {@see Conversions::toNormal()})
 * @method static string toPascalCase(string $text) Convert an identifier to PascalCase (see {@see Conversions::toPascalCase()})
 * @method static string toSnakeCase(string $text) Convert an identifier to snake_case (see {@see Conversions::toSnakeCase()})
 * @method static string[] toStrings(mixed ...$value) Convert the given strings and Stringables to an array of strings (see {@see Conversions::toStrings()})
 * @method static DateTimeZone toTimezone(DateTimeZone|string $value) Convert a value to a DateTimeZone instance (see {@see Conversions::toTimezone()})
 *
 * @uses Conversions
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Utility\Conversions' --generate='Lkrms\Facade\Convert'
 */
final class Convert extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return Conversions::class;
    }
}
