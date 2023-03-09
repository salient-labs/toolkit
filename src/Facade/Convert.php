<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Iterator;
use Lkrms\Concept\Facade;
use Lkrms\Support\DateFormatter;
use Lkrms\Utility\Conversions;

/**
 * A facade for \Lkrms\Utility\Conversions
 *
 * @method static Conversions load() Load and return an instance of the underlying Conversions class
 * @method static Conversions getInstance() Get the underlying Conversions instance
 * @method static bool isLoaded() True if an underlying Conversions instance has been loaded
 * @method static void unload() Clear the underlying Conversions instance
 * @method static int|null arrayKeyToOffset(string|int $key, array $array) Get the offset of a key in an array (see {@see Conversions::arrayKeyToOffset()})
 * @method static string arrayToCode(array $array, string $delimiter = ', ', string $arrow = ' => ') A facade for Conversions::arrayToCode()
 * @method static string classToBasename(string $class, string ...$suffixes) Remove the namespace and the first matched suffix from a class name
 * @method static string classToNamespace(string $class) Return the namespace of a class (see {@see Conversions::classToNamespace()})
 * @method static mixed coalesce(...$values) Get the first value that is not null
 * @method static string dataToQuery(array $data, bool $preserveKeys = false, ?DateFormatter $dateFormatter = null) A more API-friendly http_build_query (see {@see Conversions::dataToQuery()})
 * @method static string ellipsize(string $value, int $length) Replace the end of a multi-byte string with an ellipsis ("...") if its length exceeds a limit
 * @method static mixed emptyToNull($value) If a value is 'falsey', make it null (see {@see Conversions::emptyToNull()})
 * @method static mixed flatten($value) Recursively remove outer single-element arrays (see {@see Conversions::flatten()})
 * @method static int intervalToSeconds(DateInterval|string $value) Convert an interval to the equivalent number of seconds (see {@see Conversions::intervalToSeconds()})
 * @method static array iterableToArray(iterable $iterable, bool $preserveKeys = false) If an iterable isn't already an array, make it one
 * @method static array|object|false iterableToItem(iterable $list, string|Closure $key, $value, bool $strict = false) Return the first item in $list where the value at $key is $value (see {@see Conversions::iterableToItem()})
 * @method static Iterator iterableToIterator(iterable $iterable) If an iterable isn't already an Iterator, enclose it in one
 * @method static string linesToLists(string $text, string $separator = "\n", ?string $marker = null, string $regex = '/^\\h*[-*] /') Remove duplicates in a string where 'top-level' lines ("section names") are grouped with any subsequent 'child' lines ("list items") (see {@see Conversions::linesToLists()})
 * @method static array listToMap(array $list, string|Closure $key) Create a map from a list (see {@see Conversions::listToMap()})
 * @method static string methodToFunction(string $method) Remove the class from a method name
 * @method static string nounToPlural(string $noun) Return the plural of a singular noun
 * @method static array objectToArray(object $object) A wrapper for get_object_vars (see {@see Conversions::objectToArray()})
 * @method static array|false parseUrl(string $url) Parse a URL and return its components, including "params" if FTP parameters are present (see {@see Conversions::parseUrl()})
 * @method static string pathToBasename(string $path, int $extLimit = 0) Remove the directory and up to the given number of extensions from a path (see {@see Conversions::pathToBasename()})
 * @method static string plural(int $number, string $singular, ?string $plural = null, bool $includeNumber = false) If $number is 1, return $singular, otherwise return $plural (see {@see Conversions::plural()})
 * @method static string pluralRange(int $from, int $to, string $singular, ?string $plural = null, string $preposition = 'on') Return a phrase like "between lines 3 and 11" or "on platform 23" (see {@see Conversions::pluralRange()})
 * @method static array queryToData(string[] $query) Convert a list of "key=value" strings to an array like ["key" => "value"] (see {@see Conversions::queryToData()})
 * @method static array renameArrayKey(string|int $key, string|int $newKey, array $array) Rename an array key without changing the order of values in the array
 * @method static string resolvePath(string $path) Resolve relative segments in a pathname (see {@see Conversions::resolvePath()})
 * @method static string resolveRelativeUrl(string $embeddedUrl, string $baseUrl) Get the absolute form of a URL relative to a base URL, as per [RFC1808]
 * @method static string|false scalarToString($value) Convert a scalar to a string (see {@see Conversions::scalarToString()})
 * @method static int sizeToBytes(string $size) Convert php.ini values like "128M" to bytes (see {@see Conversions::sizeToBytes()})
 * @method static string sparseToString(string $separator, array $array) Remove zero-width values from an array before imploding it
 * @method static string[] stringToList(string $separator, string $string, ?string $trim = null) Explode a string, trim each substring, remove empty strings
 * @method static string[] stringsToUniqueList(string[] $array) A faster array_unique with reindexing
 * @method static array toArray($value, bool $emptyIfNull = false) If a value isn't an array, make it the first element of one (see {@see Conversions::toArray()})
 * @method static string toCamelCase(string $text) Convert an identifier to camelCase
 * @method static string toCase(string $text, int $case = self::IDENTIFIER_CASE_SNAKE) Perform the given case conversion
 * @method static DateTimeImmutable toDateTimeImmutable(DateTimeInterface $date) A shim for DateTimeImmutable::createFromInterface() (PHP 8+)
 * @method static int|null toIntOrNull($value) Cast a value to an integer, preserving null
 * @method static string toKebabCase(string $text) Convert an identifier to kebab-case
 * @method static array toList($value, bool $emptyIfNull = false) If a value isn't a list, make it the first element of one (see {@see Conversions::toList()})
 * @method static string toNormal(string $text) Clean up a string for comparison with other strings (see {@see Conversions::toNormal()})
 * @method static string toPascalCase(string $text) Convert an identifier to PascalCase
 * @method static array toScalarArray(array $array) JSON-encode non-scalar values in an array (see {@see Conversions::toScalarArray()})
 * @method static string toShellArg(string $value) A platform-agnostic escapeshellarg that only adds quotes if necessary
 * @method static string toSnakeCase(string $text) Convert an identifier to snake_case
 * @method static string[] toStrings(...$value) Convert the given strings and Stringables to an array of strings
 * @method static DateTimeZone toTimezone(DateTimeZone|string $value) Convert a value to a DateTimeZone instance
 * @method static array toUniqueList(array $array) A type-agnostic array_unique with reindexing
 * @method static string unparseUrl(array $url) Convert a parse_url array to a string (see {@see Conversions::unparseUrl()})
 * @method static string unwrap(string $string, string $break = "\n") Undo wordwrap()
 * @method static string uuidToHex(string $bytes) Convert a 16-byte UUID to its 36-byte hexadecimal representation
 * @method static string valueToCode($value, string $delimiter = ', ', string $arrow = ' => ') A facade for Conversions::valueToCode()
 *
 * @uses Conversions
 * @extends Facade<Conversions>
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Utility\Conversions' 'Lkrms\Facade\Convert'
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

    /**
     * array_splice for associative arrays
     *
     * @param string|int $key
     * @see Conversions::arraySpliceAtKey()
     */
    public static function arraySpliceAtKey(array &$array, $key, ?int $length = null, array $replacement = []): array
    {
        static::setFuncNumArgs(__FUNCTION__, func_num_args());
        try {
            return static::getInstance()->arraySpliceAtKey($array, $key, $length, $replacement);
        } finally {
            static::clearFuncNumArgs(__FUNCTION__);
        }
    }

    /**
     * A type-agnostic multi-column array_unique with reindexing
     *
     * @see Conversions::columnsToUniqueList()
     */
    public static function columnsToUniqueList(array $array, array &...$columns): array
    {
        static::setFuncNumArgs(__FUNCTION__, func_num_args());
        try {
            return static::getInstance()->columnsToUniqueList($array, ...$columns);
        } finally {
            static::clearFuncNumArgs(__FUNCTION__);
        }
    }
}
