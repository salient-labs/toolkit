<?php declare(strict_types=1);

namespace Salient\Utility;

use Salient\Contract\Core\Arrayable;
use Salient\Utility\Internal\Copier;
use Salient\Utility\Internal\Exporter;
use Closure;
use Countable;
use InvalidArgumentException;
use ReflectionClass;
use Stringable;
use Traversable;

/**
 * Extract, convert and generate data
 *
 * @api
 */
final class Get extends AbstractUtility
{
    /**
     * Do not throw an exception if an uncloneable object is encountered
     */
    public const COPY_SKIP_UNCLONEABLE = 1;

    /**
     * Assign values to properties by reference
     *
     * Required if an object graph contains nodes with properties passed or
     * assigned by reference.
     */
    public const COPY_BY_REFERENCE = 2;

    /**
     * Take a shallow copy of objects with a __clone method
     */
    public const COPY_TRUST_CLONE = 4;

    /**
     * Copy service containers
     */
    public const COPY_CONTAINERS = 8;

    /**
     * Copy singletons
     */
    public const COPY_SINGLETONS = 16;

    /**
     * Cast a value to boolean, converting boolean strings and preserving null
     *
     * @param mixed $value
     * @return ($value is null ? null : bool)
     */
    public static function boolean($value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (is_string($value) && Regex::match(
            '/^' . Regex::BOOLEAN_STRING . '$/',
            trim($value),
            $matches,
            \PREG_UNMATCHED_AS_NULL
        )) {
            return $matches['true'] !== null;
        }

        return (bool) $value;
    }

    /**
     * Cast a value to integer, preserving null
     *
     * @param int|float|string|bool|null $value
     * @return ($value is null ? null : int)
     */
    public static function integer($value): ?int
    {
        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Cast a value to the array-key it appears to be, preserving null
     *
     * @param int|string|null $value
     * @return ($value is null ? null : ($value is int ? int : int|string))
     */
    public static function arrayKey($value)
    {
        if ($value === null || is_int($value)) {
            return $value;
        }

        if (Regex::match('/^' . Regex::INTEGER_STRING . '$/', trim($value))) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * Convert a callable to a closure
     *
     * @return ($callable is null ? null : Closure)
     */
    public static function closure(?callable $callable): ?Closure
    {
        return $callable === null || $callable instanceof Closure
            ? $callable
            : Closure::fromCallable($callable);
    }

    /**
     * Resolve a closure to its return value
     *
     * @template T
     * @template TArg
     *
     * @param T|Closure(TArg...): T $value
     * @param TArg ...$args Passed to `$value` if it is a closure.
     * @return T
     */
    public static function value($value, ...$args)
    {
        return $value instanceof Closure
            ? $value(...$args)
            : $value;
    }

    /**
     * Convert "key[=value]" pairs to an associative array
     *
     * @param string[] $values
     * @return mixed[]
     */
    public static function filter(array $values, bool $discardInvalid = false): array
    {
        $valid = Regex::grep('/^[^ .=]++/', $values);
        if (!$discardInvalid && $valid !== $values) {
            $invalid = array_diff($values, $valid);
            throw new InvalidArgumentException(Inflect::format(
                $invalid,
                "Invalid key[=value] {{#:pair}}: '%s'",
                implode("', '", $invalid),
            ));
        }

        /** @var int|null */
        static $maxInputVars;
        $maxInputVars ??= (int) ini_get('max_input_vars');
        if (count($valid) > $maxInputVars) {
            throw new InvalidArgumentException(sprintf(
                'Key-value pairs exceed max_input_vars (%d)',
                $maxInputVars,
            ));
        }

        $values = Regex::replaceCallback(
            '/^([^=]++)(?:=(.++))?/s',
            fn($matches) =>
                rawurlencode((string) $matches[1])
                . ($matches[2] === null
                    ? ''
                    : '=' . rawurlencode($matches[2])),
            $valid,
            -1,
            $count,
            \PREG_UNMATCHED_AS_NULL,
        );
        $query = [];
        parse_str(implode('&', $values), $query);
        return $query;
    }

    /**
     * Get the first value that is not null, or return the last value
     *
     * @template T
     *
     * @param T|null ...$values
     * @return T|null
     */
    public static function coalesce(...$values)
    {
        $value = null;
        foreach ($values as $value) {
            if ($value !== null) {
                return $value;
            }
        }
        return $value;
    }

    /**
     * Resolve a value to an array
     *
     * @template TKey of array-key
     * @template TValue
     *
     * @param Arrayable<TKey,TValue>|iterable<TKey,TValue> $value
     * @return array<TKey,TValue>
     */
    public static function array($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }
        return $value->toArray();
    }

    /**
     * Resolve a value to a list
     *
     * @template TValue
     *
     * @param Arrayable<array-key,TValue>|iterable<TValue> $value
     * @return list<TValue>
     */
    public static function list($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if ($value instanceof Traversable) {
            return iterator_to_array($value, false);
        }
        return $value->toArray(false);
    }

    /**
     * Resolve a value to an item count
     *
     * @param Arrayable<array-key,mixed>|iterable<array-key,mixed>|Countable|int $value
     */
    public static function count($value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_array($value) || $value instanceof Countable) {
            return count($value);
        }
        if ($value instanceof Arrayable) {
            return count($value->toArray(false));
        }
        return iterator_count($value);
    }

    /**
     * Get the unqualified name of a class, optionally removing the first
     * matching suffix
     *
     * @return ($class is class-string ? non-empty-string : string)
     */
    public static function basename(string $class, string ...$suffix): string
    {
        /** @var string */
        $class = strrchr('\\' . $class, '\\');
        $class = substr($class, 1);

        if (!$suffix) {
            return $class;
        }

        foreach ($suffix as $suffix) {
            if ($suffix === $class) {
                continue;
            }
            $length = strlen($suffix);
            if (substr($class, -$length) === $suffix) {
                return substr($class, 0, -$length);
            }
        }

        return $class;
    }

    /**
     * Get the namespace of a class
     */
    public static function namespace(string $class): string
    {
        $length = strrpos('\\' . $class, '\\') - 1;

        return $length < 1
            ? ''
            : trim(substr($class, 0, $length), '\\');
    }

    /**
     * Normalise a class name for comparison
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @return class-string<T>
     */
    public static function fqcn(string $class): string
    {
        /** @var class-string<T> */
        return Str::lower(ltrim($class, '\\'));
    }

    /**
     * Get a UUID in raw binary form
     *
     * If `$uuid` is not given, an \[RFC9562]-compliant UUIDv4 is generated.
     *
     * @throws InvalidArgumentException if an invalid UUID is given.
     */
    public static function binaryUuid(?string $uuid = null): string
    {
        return $uuid === null
            ? self::getUuid(true)
            : self::normaliseUuid($uuid, true);
    }

    /**
     * Get a UUID in hexadecimal form
     *
     * If `$uuid` is not given, an \[RFC9562]-compliant UUIDv4 is generated.
     *
     * @throws InvalidArgumentException if an invalid UUID is given.
     */
    public static function uuid(?string $uuid = null): string
    {
        return $uuid === null
            ? self::getUuid(false)
            : self::normaliseUuid($uuid, false);
    }

    private static function getUuid(bool $binary): string
    {
        $uuid = [
            // random_a (bits 0-31)
            random_bytes(4),
            // random_a (bits 32-47)
            random_bytes(2),
            // ver (bits 48-51 = 0b0100 = 4), random_b (bits 52-63)
            chr(random_int(0, 0xF) | 0x40) . random_bytes(1),
            // var (bits 64-65 = 0b10 = 2), random_c (bits 66-79)
            chr(random_int(0, 0x3F) | 0x80) . random_bytes(1),
            // random_c (bits 80-127)
            random_bytes(6),
        ];

        if ($binary) {
            return implode('', $uuid);
        }

        foreach ($uuid as $bin) {
            $hex[] = bin2hex($bin);
        }

        return implode('-', $hex);
    }

    private static function normaliseUuid(string $uuid, bool $binary): string
    {
        $length = strlen($uuid);

        if ($length !== 16) {
            $uuid = str_replace('-', '', $uuid);

            if (!Regex::match('/^[0-9a-f]{32}$/i', $uuid)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid UUID: %s',
                    $uuid,
                ));
            }

            if ($binary) {
                /** @var string */
                return hex2bin($uuid);
            }

            $uuid = Str::lower($uuid);

            return implode('-', [
                substr($uuid, 0, 8),
                substr($uuid, 8, 4),
                substr($uuid, 12, 4),
                substr($uuid, 16, 4),
                substr($uuid, 20, 12),
            ]);
        }

        if ($binary) {
            return $uuid;
        }

        $uuid = [
            substr($uuid, 0, 4),
            substr($uuid, 4, 2),
            substr($uuid, 6, 2),
            substr($uuid, 8, 2),
            substr($uuid, 10, 6),
        ];

        foreach ($uuid as $bin) {
            $hex[] = bin2hex($bin);
        }

        return implode('-', $hex);
    }

    /**
     * Get a sequence of random characters
     *
     * @param non-empty-string $chars
     */
    public static function randomText(int $length, string $chars = Str::ALPHANUMERIC): string
    {
        $max = strlen($chars) - 1;
        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= $chars[random_int(0, $max)];
        }
        return $text;
    }

    /**
     * Get the hash of a value in raw binary form
     *
     * @param int|float|string|bool|Stringable|null $value
     */
    public static function binaryHash($value): string
    {
        // xxHash isn't supported until PHP 8.1, so MD5 is the best fit
        return hash('md5', (string) $value, true);
    }

    /**
     * Get the hash of a value in hexadecimal form
     *
     * @param int|float|string|bool|Stringable|null $value
     */
    public static function hash($value): string
    {
        return hash('md5', (string) $value);
    }

    /**
     * Get the type of a variable
     *
     * @param mixed $value
     */
    public static function type($value): string
    {
        if (is_object($value)) {
            return (new ReflectionClass($value))->isAnonymous()
                ? 'class@anonymous'
                : get_class($value);
        }

        if (is_resource($value)) {
            return sprintf('resource (%s)', get_resource_type($value));
        }

        $type = gettype($value);
        return [
            'boolean' => 'bool',
            'integer' => 'int',
            'double' => 'float',
            'NULL' => 'null',
        ][$type] ?? $type;
    }

    /**
     * Get php.ini values like "128M" in bytes
     *
     * From the PHP FAQ: "The available options are K (for Kilobytes), M (for
     * Megabytes) and G (for Gigabytes), and are all case-insensitive. Anything
     * else assumes bytes. 1M equals one Megabyte or 1048576 bytes. 1K equals
     * one Kilobyte or 1024 bytes."
     */
    public static function bytes(string $size): int
    {
        // PHP is very forgiving with the syntax of these values
        $size = rtrim($size);
        $exp = [
            'K' => 1,
            'k' => 1,
            'M' => 2,
            'm' => 2,
            'G' => 3,
            'g' => 3,
        ][$size[-1] ?? ''] ?? 0;
        return (int) $size * 1024 ** $exp;
    }

    /**
     * Convert a value to PHP code
     *
     * Similar to {@see var_export()}, but with more economical output.
     *
     * @param mixed $value
     * @param non-empty-string[] $classes Strings in this array are output as
     * `<string>::class` instead of `'<string>'`.
     * @param array<non-empty-string,string> $constants An array that maps
     * strings to constant identifiers, e.g. `[\PHP_EOL => '\PHP_EOL']`.
     */
    public static function code(
        $value,
        string $delimiter = ', ',
        string $arrow = ' => ',
        ?string $escapeCharacters = null,
        string $tab = '    ',
        array $classes = [],
        array $constants = []
    ): string {
        return (new Exporter(
            $delimiter,
            $arrow,
            $escapeCharacters,
            $tab,
            $classes,
            $constants,
        ))->export($value);
    }

    /**
     * Get the end-of-line sequence used in a string
     *
     * Recognised line endings are LF (`"\n"`), CRLF (`"\r\n"`) and CR (`"\r"`).
     *
     * @see File::getEol()
     * @see Str::setEol()
     *
     * @return non-empty-string|null `null` if there are no recognised newline
     * characters in `$string`.
     */
    public static function eol(string $string): ?string
    {
        $lfPos = strpos($string, "\n");

        if ($lfPos === false) {
            return strpos($string, "\r") === false
                ? null
                : "\r";
        }

        if ($lfPos && $string[$lfPos - 1] === "\r") {
            return "\r\n";
        }

        return "\n";
    }

    /**
     * Get a deep copy of a value
     *
     * @template T
     *
     * @param T $value
     * @param class-string[]|(Closure(object): (object|bool)) $skip A list of
     * classes to skip, or a closure that returns:
     * - `true` if the object should be skipped
     * - `false` if the object should be copied normally, or
     * - a copy of the object
     * @param int-mask-of<Get::COPY_*> $flags
     * @return T
     */
    public static function copy(
        $value,
        $skip = [],
        int $flags = Get::COPY_SKIP_UNCLONEABLE | Get::COPY_BY_REFERENCE
    ) {
        return (new Copier($skip, $flags))->copy($value);
    }
}
