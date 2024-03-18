<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Char;
use Salient\Contract\Core\CopyFlag;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\QueryFlag;
use Salient\Contract\Core\Regex;
use Salient\Core\Exception\InvalidArgumentException;
use Salient\Core\Exception\InvalidArgumentTypeException;
use Salient\Core\Exception\UncloneableObjectException;
use Salient\Core\AbstractUtility;
use Salient\Core\DateFormatter;
use Closure;
use Countable;
use DateTimeInterface;
use DateTimeZone;
use ReflectionClass;
use ReflectionObject;
use Stringable;
use Traversable;
use UnitEnum;

/**
 * Get values from other values
 */
final class Get extends AbstractUtility
{
    /**
     * Throw an exception if a value is null, otherwise return it
     *
     * @template T
     *
     * @param T $value
     * @return (T is null ? never : T)
     * @phpstan-param T|null $value
     * @phpstan-return ($value is null ? never : T)
     */
    public static function notNull($value)
    {
        if ($value === null) {
            throw new InvalidArgumentException('$value cannot be null');
        }

        return $value;
    }

    /**
     * Cast a value to boolean, converting boolean strings and preserving null
     *
     * @see Test::isBoolValue()
     *
     * @param mixed $value
     * @return ($value is null ? null : bool)
     */
    public static function boolean($value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (is_string($value) && Pcre::match(
            '/^' . Regex::BOOLEAN_STRING . '$/',
            $value,
            $match,
            \PREG_UNMATCHED_AS_NULL
        )) {
            return $match['true'] !== null;
        }

        return (bool) $value;
    }

    /**
     * Cast a value to integer, preserving null
     *
     * @param mixed $value
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

        if (!is_string($value)) {
            throw new InvalidArgumentTypeException(1, 'value', 'int|string|null', $value);
        }

        if (Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * Resolve a closure to its return value
     *
     * @template T
     *
     * @param (Closure(mixed...): T)|T $value
     * @param mixed ...$args Passed to `$value` if it is a closure.
     * @return T
     */
    public static function value($value, ...$args)
    {
        if ($value instanceof Closure) {
            return $value(...$args);
        }
        return $value;
    }

    /**
     * Convert "key=value" pairs to an associative array
     *
     * @param string[] $values
     * @return array<string,mixed>
     */
    public static function filter(array $values): array
    {
        $values = Pcre::replaceCallback(
            '/^([^=]++)=(.++)/s',
            fn(array $match) =>
                $match[1] . '=' . rawurlencode($match[2]),
            $values,
        );

        $query = [];
        parse_str(implode('&', $values), $query);
        return $query;
    }

    /**
     * Convert nested arrays to a query string
     *
     * List keys are not preserved by default. Use `$flag` to modify this
     * behaviour.
     *
     * @param mixed[] $data
     * @param int-mask-of<QueryFlag::*> $flags
     */
    public static function query(
        array $data,
        int $flags = QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null
    ): string {
        return implode('&', self::doQuery(
            $data,
            $flags,
            $dateFormatter ?: new DateFormatter()
        ));
    }

    /**
     * @param mixed[] $data
     * @param int-mask-of<QueryFlag::*> $flags
     * @param string[] $query
     * @return string[]
     */
    private static function doQuery(
        array $data,
        int $flags,
        DateFormatterInterface $df,
        array &$query = [],
        string $keyPrefix = '',
        string $keyFormat = '%s'
    ): array {
        foreach ($data as $key => $value) {
            $key = $keyPrefix . sprintf($keyFormat, $key);

            if (is_array($value)) {
                if (!$value) {
                    continue;
                }
                $format = (
                    Arr::isList($value)
                        ? $flags & QueryFlag::PRESERVE_LIST_KEYS
                        : (Arr::isIndexed($value)
                            ? $flags & QueryFlag::PRESERVE_NUMERIC_KEYS
                            : $flags & QueryFlag::PRESERVE_STRING_KEYS)
                ) ? '[%s]' : '[]';
                self::doQuery($value, $flags, $df, $query, $key, $format);
                continue;
            }

            if (is_bool($value)) {
                $value = (string) (int) $value;
            } elseif ($value instanceof DateTimeInterface) {
                $value = $df->format($value);
            } else {
                $value = (string) $value;
            }

            $query[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

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
            if ($value === null) {
                continue;
            }
            return $value;
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
    public static function array($value, bool $preserveKeys = false): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }
        return iterator_to_array($value, $preserveKeys);
    }

    /**
     * Resolve a value to an item count
     *
     * @param Traversable<array-key,mixed>|Arrayable<array-key,mixed>|Countable|array<array-key,mixed>|int $value
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
            return count($value->toArray());
        }
        return iterator_count($value);
    }

    /**
     * Get the unqualified name of a class, optionally removing a suffix
     *
     * Only the first matching `$suffix` is removed, so longer suffixes should
     * be given first.
     */
    public static function basename(string $class, string ...$suffix): string
    {
        $class = substr(strrchr('\\' . $class, '\\'), 1);

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
        return Str::lower(ltrim($class, '\\'));
    }

    /**
     * Get a UUID in raw binary form
     *
     * If `$uuid` is not given, an \[RFC4122]-compliant UUID is generated.
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
     * If `$uuid` is not given, an \[RFC4122]-compliant UUID is generated.
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
            random_bytes(4),
            random_bytes(2),
            // Version 4 (most significant 4 bits = 0b0100)
            chr(random_int(0, 0x0F) | 0x40) . random_bytes(1),
            // Variant 1 (most significant 2 bits = 0b10)
            chr(random_int(0, 0x3F) | 0x80) . random_bytes(1),
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

            if (!Pcre::match('/^[0-9a-f]{32}$/i', $uuid)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid UUID: %s',
                    $uuid,
                ));
            }

            if ($binary) {
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
     */
    public static function randomText(int $length, string $chars = Char::ALPHANUMERIC): string
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
        return hash('md5', $value, true);
    }

    /**
     * Get the hash of a value in hexadecimal form
     *
     * @param int|float|string|bool|Stringable|null $value
     */
    public static function hash($value): string
    {
        return hash('md5', $value);
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
            'K' => 1, 'k' => 1, 'M' => 2, 'm' => 2, 'G' => 3, 'g' => 3
        ][$size[-1] ?? ''] ?? 0;
        return (int) $size * 1024 ** $exp;
    }

    /**
     * Convert a value to PHP code
     *
     * Similar to {@see var_export()}, but with more economical output.
     *
     * @param mixed $value
     * @param string[] $classes Strings found in this array are output as
     * `<string>::class` instead of `'<string>'`.
     */
    public static function code(
        $value,
        string $delimiter = ', ',
        string $arrow = ' => ',
        ?string $escapeCharacters = null,
        string $tab = '    ',
        array $classes = []
    ): string {
        $eol = (string) self::eol($delimiter);
        $multiline = (bool) $eol;
        $classes = Arr::toIndex($classes);
        return self::doCode(
            $value,
            $delimiter,
            $arrow,
            $escapeCharacters,
            $tab,
            $classes,
            $multiline,
            $eol,
        );
    }

    /**
     * @param mixed $value
     * @param array<string,true> $classes
     */
    private static function doCode(
        $value,
        string $delimiter,
        string $arrow,
        ?string $escapeCharacters,
        string $tab,
        array $classes,
        bool $multiline,
        string $eol,
        string $indent = ''
    ): string {
        if ($value === null) {
            return 'null';
        }

        if ($classes && is_string($value) && ($classes[$value] ?? false)) {
            return $value . '::class';
        }

        // Escape strings that contain vertical whitespace or characters in
        // `$escapeCharacters`
        if (is_string($value) && (
            Pcre::match('/\v/', $value) || (
                $escapeCharacters !== null &&
                $escapeCharacters !== '' &&
                strpbrk($value, $escapeCharacters) !== false
            )
        )) {
            $escaped = addcslashes($value, "\0..\x1f\$\\" . $escapeCharacters);

            // Replace characters in `$escapeCharacters` with the equivalent
            // hexadecimal escape
            if ($escapeCharacters !== null && $escapeCharacters !== '') {
                $search = [];
                $replace = [];
                foreach (str_split($escapeCharacters) as $character) {
                    $regex = preg_quote(addcslashes($character, $character), '/');
                    $search[] = "/((?<!\\\\)(?:\\\\\\\\)*){$regex}/";
                    $replace[] = sprintf('$1\x%02x', ord($character));
                }
                $escaped = Pcre::replace($search, $replace, $escaped);
            }

            // Convert octal notation to hexadecimal (e.g. "\177" to "\x7f") and
            // correct for differences between C and PHP escape sequences:
            // - recognised by PHP: \0 \e \f \n \r \t \v
            // - applied by addcslashes: \000 \033 \a \b \f \n \r \t \v
            Pcre::replaceCallback(
                '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?<NUL>000(?![0-7]))|(?<octal>[0-7]{3})|(?<cslash>[ab]))/',
                fn(array $matches) =>
                    $matches[1]
                    . ($matches['NUL'] !== null
                        ? '\0'
                        : ($matches['octal'] !== null
                            ? (($dec = octdec($matches['octal'])) === 27
                                ? '\e'
                                : sprintf('\x%02x', $dec))
                            : sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']]))),
                $escaped,
                -1,
                $count,
                \PREG_UNMATCHED_AS_NULL,
            );

            return '"' . $escaped . '"';
        }

        if (!is_array($value)) {
            $result = var_export($value, true);
            if (is_float($value)) {
                return Str::lower($result);
            }
            return $result;
        }

        if (!$value) {
            return '[]';
        }

        $prefix = '[';
        $suffix = ']';
        $glue = $delimiter;

        if ($multiline) {
            $suffix = $delimiter . $indent . $suffix;
            $indent .= $tab;
            $prefix .= $eol . $indent;
            $glue .= $indent;
        }

        $isList = Arr::isList($value);
        foreach ($value as $key => $value) {
            $value = self::doCode($value, $delimiter, $arrow, $escapeCharacters, $tab, $classes, $multiline, $eol, $indent);
            if ($isList) {
                $values[] = $value;
                continue;
            }
            $key = self::doCode($key, $delimiter, $arrow, $escapeCharacters, $tab, $classes, $multiline, $eol, $indent);
            $values[] = $key . $arrow . $value;
        }

        return $prefix . implode($glue, $values) . $suffix;
    }

    /**
     * Get the end-of-line sequence used in a string
     *
     * Recognised line endings are LF (`"\n"`), CRLF (`"\r\n"`) and CR (`"\r"`).
     *
     * @return string|null `null` if there are no recognised line breaks in
     * `$string`.
     *
     * @see Filesystem::getEol()
     * @see Str::setEol()
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
     * Get a deep copy of an object
     *
     * @template T of object
     *
     * @param T $object
     * @param class-string[] $skip
     * @param int-mask-of<CopyFlag::*> $flags
     * @return T
     */
    public static function copy(
        object $object,
        array $skip = [],
        int $flags = CopyFlag::SKIP_UNCLONEABLE | CopyFlag::ASSIGN_PROPERTIES_BY_REFERENCE
    ): object {
        return self::doCopy($object, $skip, $flags);
    }

    /**
     * @template T of resource|mixed[]|object|int|float|string|bool|null
     *
     * @param T $var
     * @param class-string[] $skip
     * @param int-mask-of<CopyFlag::*> $flags
     * @param array<int,object> $map
     * @return T
     */
    private static function doCopy(
        $var,
        array $skip,
        int $flags,
        array &$map = []
    ) {
        if (is_resource($var)) {
            return $var;
        }

        if (is_array($var)) {
            /** @var array<resource|mixed[]|object|int|float|string|bool|null> $var */
            foreach ($var as $key => $value) {
                $array[$key] = self::doCopy($value, $skip, $flags, $map);
            }
            return $array ?? [];
        }

        if (!is_object($var) || $var instanceof UnitEnum) {
            return $var;
        }

        $id = spl_object_id($var);
        if (isset($map[$id])) {
            return $map[$id];
        }

        if ((
            !($flags & CopyFlag::COPY_CONTAINERS) &&
            $var instanceof PsrContainerInterface
        ) || (
            !($flags & CopyFlag::COPY_SINGLETONS) &&
            $var instanceof SingletonInterface
        )) {
            $map[$id] = $var;
            return $var;
        }

        foreach ($skip as $class) {
            if (is_a($var, $class)) {
                $map[$id] = $var;
                return $var;
            }
        }

        $_var = new ReflectionObject($var);

        if (!$_var->isCloneable()) {
            if ($flags & CopyFlag::SKIP_UNCLONEABLE) {
                $map[$id] = $var;
                return $var;
            }

            throw new UncloneableObjectException(
                sprintf('%s cannot be copied', $_var->getName())
            );
        }

        $clone = clone $var;
        $map[$id] = $clone;
        $id = spl_object_id($clone);
        $map[$id] = $clone;

        if (
            $flags & CopyFlag::TRUST_CLONE_METHODS &&
            $_var->hasMethod('__clone')
        ) {
            return $clone;
        }

        if (
            $clone instanceof DateTimeInterface ||
            $clone instanceof DateTimeZone
        ) {
            return $clone;
        }

        $byRef = (bool) ($flags & CopyFlag::ASSIGN_PROPERTIES_BY_REFERENCE) &&
            !$_var->isInternal();
        foreach (Reflect::getAllProperties($_var) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);

            if (!$property->isInitialized($clone)) {
                continue;
            }

            $name = $property->getName();
            /** @var resource|mixed[]|object|int|float|string|bool|null */
            $value = $property->getValue($clone);
            $value = self::doCopy($value, $skip, $flags, $map);

            if (!$byRef) {
                $property->setValue($clone, $value);
                continue;
            }

            (function () use ($name, $value): void {
                // @phpstan-ignore-next-line
                $this->$name = &$value;
            })->bindTo($clone, $property->getDeclaringClass()->getName())();
        }

        return $clone;
    }
}
