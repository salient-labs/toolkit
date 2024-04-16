<?php declare(strict_types=1);

namespace Salient\Core\Utility;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Salient\Contract\Container\SingletonInterface;
use Salient\Contract\Core\Arrayable;
use Salient\Contract\Core\Char;
use Salient\Contract\Core\CopyFlag;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\Jsonable;
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
use JsonSerializable;
use ReflectionClass;
use ReflectionObject;
use Stringable;
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
     * @see Test::isBoolean()
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

        if (!is_string($value)) {
            throw new InvalidArgumentTypeException(1, 'value', 'int|string|null', $value);
        }

        if (Pcre::match('/^' . Regex::INTEGER_STRING . '$/', $value)) {
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
        return $callable === null
            ? null
            : ($callable instanceof Closure
                ? $callable
                : Closure::fromCallable($callable));
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
     * Convert "key[=value]" pairs to an associative array
     *
     * @param string[] $values
     * @return mixed[]
     */
    public static function filter(array $values, bool $discardInvalid = true): array
    {
        $valid = Pcre::grep('/^[^ .=]++/', $values);
        if (!$discardInvalid && $valid !== $values) {
            $invalid = array_diff($values, $valid);
            throw new InvalidArgumentException(Inflect::format(
                $invalid,
                "Invalid key-value {{#:pair}}: '%s'",
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

        $values = Pcre::replaceCallback(
            '/^([^=]++)(?:=(.++))?/s',
            fn(array $match) =>
                rawurlencode((string) $match[1])
                . ($match[2] === null
                    ? ''
                    : '=' . rawurlencode($match[2])),
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
     * Convert nested arrays and objects to a query string
     *
     * @see Get::formData() for details.
     *
     * @template T of object|mixed[]|string|null
     *
     * @param mixed[] $data
     * @param int-mask-of<QueryFlag::*> $flags
     * @param (callable(object): (T|false))|null $callback
     */
    public static function query(
        array $data,
        int $flags = QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null,
        ?callable $callback = null
    ): string {
        $data = self::doFormData(
            $data,
            $flags,
            $dateFormatter ?? new DateFormatter(),
            $callback,
        );

        foreach ($data as [$key, $value]) {
            if (!is_string($value)) {
                throw new InvalidArgumentException(sprintf(
                    "Invalid value at '%s': %s",
                    $key,
                    self::type($value),
                ));
            }
            $query[] = rawurlencode($key) . '=' . rawurlencode($value);
        }

        return implode('&', $query ?? []);
    }

    /**
     * Convert nested arrays and objects to HTML form data
     *
     * List keys are not preserved by default. Use `$flags` to modify this
     * behaviour.
     *
     * If no `$dateFormatter` is given, a {@see DateFormatter} is created to
     * convert {@see DateTimeInterface} instances to ISO-8601 strings.
     *
     * `$callback` is applied to objects other than {@see DateTimeInterface}
     * instances found in `$data`. It may return `null` to skip the value, or
     * `false` to process the value as if no callback had been given. If a
     * {@see DateTimeInterface} is returned, it is converted to `string` as per
     * the `$dateFormatter` note above.
     *
     * If no `$callback` is given, objects are resolved as follows:
     *
     * - {@see DateTimeInterface}: converted to `string` (see `$dateFormatter`
     *   note above)
     * - {@see Arrayable}: replaced with {@see Arrayable::toArray()}
     * - {@see JsonSerializable}: replaced with
     *   {@see JsonSerializable::jsonSerialize()} if it returns an `array`
     * - {@see Jsonable}: replaced with {@see Jsonable::toJson()} after decoding
     *   if {@see json_decode()} returns an `array`
     * - `object` with at least one public property: replaced with an array that
     *   maps public property names to values
     * - {@see Stringable}: cast to `string`
     *
     * @template T of object|mixed[]|string|null
     *
     * @param mixed[]|object $data
     * @param int-mask-of<QueryFlag::*> $flags
     * @param (callable(object): (T|false))|null $callback
     * @return list<array{string,string|(T&object)}>
     */
    public static function formData(
        $data,
        int $flags = QueryFlag::PRESERVE_NUMERIC_KEYS | QueryFlag::PRESERVE_STRING_KEYS,
        ?DateFormatterInterface $dateFormatter = null,
        ?callable $callback = null
    ) {
        /** @var list<array{string,string|(T&object)}> */
        return self::doFormData(
            $data,
            $flags,
            $dateFormatter ?? new DateFormatter(),
            $callback,
        );
    }

    /**
     * @template T of object|mixed[]|string|null
     *
     * @param object|mixed[]|string|null $data
     * @param int-mask-of<QueryFlag::*> $flags
     * @param (callable(object): (T|false))|null $cb
     * @param list<array{string,string|(T&object)}> $query
     * @return list<array{string,string|(T&object)}>
     */
    private static function doFormData(
        $data,
        int $flags,
        DateFormatterInterface $df,
        ?callable $cb,
        array &$query = [],
        string $name = ''
    ): array {
        if ($name === '') {
            $data = self::getFormDataValue($data, $df, $cb);
        }

        /** @var (T&object)|mixed[]|string|null $data */
        if ($data === null || $data === []) {
            return $query;
        }

        if (is_array($data)) {
            $preserveKeys = $name === '' || (
                Arr::isList($data)
                    ? $flags & QueryFlag::PRESERVE_LIST_KEYS
                    : (Arr::isIndexed($data)
                        ? $flags & QueryFlag::PRESERVE_NUMERIC_KEYS
                        : $flags & QueryFlag::PRESERVE_STRING_KEYS)
            );
            $format = $preserveKeys ? ($name === '' ? '%s' : '[%s]') : '[]';

            $hasArray = false;
            foreach ($data as &$value) {
                $value = self::getFormDataValue($value, $df, $cb);
                if (!$preserveKeys && !$hasArray && is_array($value)) {
                    $hasArray = true;
                }
            }
            unset($value);

            if ($hasArray) {
                $format = '[%s]';
            }

            /** @var object|mixed[]|string|null $value */
            foreach ($data as $key => $value) {
                $key = sprintf($format, $key);
                self::doFormData($value, $flags, $df, $cb, $query, $name . $key);
            }

            return $query;
        }

        $query[] = [$name, $data];

        return $query;
    }

    /**
     * @template T of object|mixed[]|string|null
     *
     * @param mixed $value
     * @param (callable(object): (T|false))|null $cb
     * @return mixed[]|string|T
     */
    private static function getFormDataValue(
        $value,
        DateFormatterInterface $df,
        ?callable $cb
    ) {
        if (is_bool($value)) {
            return (string) (int) $value;
        }

        if ($value === null || is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $df->format($value);
        }

        if (is_object($value)) {
            if ($cb !== null) {
                $result = $cb($value);
                if ($result instanceof DateTimeInterface) {
                    return $df->format($result);
                }
                if ($result !== false) {
                    return $result;
                }
            }

            if ($value instanceof Arrayable) {
                return $value->toArray();
            }

            if ((
                $value instanceof JsonSerializable &&
                is_array($result = $value->jsonSerialize())
            ) || (
                $value instanceof Jsonable &&
                is_array($result = Json::parseObjectAsArray($value->toJson()))
            )) {
                return $result;
            }

            // Get public property values
            $result = [];
            // @phpstan-ignore-next-line
            foreach ($value as $key => $val) {
                $result[$key] = $val;
            }
            if ($result !== []) {
                return $result;
            }

            if (Test::isStringable($value)) {
                return (string) $value;
            }

            return [];
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid value: %s',
            self::type($value),
        ));
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
    public static function array($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }
        return iterator_to_array($value);
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
     */
    public static function randomText(int $length, string $chars = Char::ALPHANUMERIC): string
    {
        if ($chars === '') {
            throw new InvalidArgumentException('Argument #1 ($chars) must be a non-empty string');
        }
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
        $eol = (string) self::eol($delimiter);
        $multiline = (bool) $eol;
        $escapeRegex = null;
        $search = [];
        $replace = [];
        if ($escapeCharacters !== null && $escapeCharacters !== '') {
            $escapeRegex = Pcre::quoteCharacterClass($escapeCharacters, '/');
            foreach (str_split($escapeCharacters) as $character) {
                $search[] = sprintf(
                    '/((?<!\\\\)(?:\\\\\\\\)*)%s/',
                    preg_quote(addcslashes($character, $character), '/'),
                );
                $replace[] = sprintf('$1\x%02x', ord($character));
            }
        }
        $classes = Arr::toIndex($classes);
        $constRegex = [];
        foreach (array_keys($constants) as $string) {
            $constRegex[] = preg_quote($string, '/');
        }
        switch (count($constRegex)) {
            case 0:
                $constRegex = null;
                break;
            case 1:
                $constRegex = '/' . $constRegex[0] . '/';
                break;
            default:
                $constRegex = '/(?:' . implode('|', $constRegex) . ')/';
                break;
        }
        return self::doCode(
            $value,
            $delimiter,
            $arrow,
            $escapeCharacters,
            $escapeRegex,
            $search,
            $replace,
            $tab,
            $classes,
            $constants,
            $constRegex,
            $multiline,
            $eol,
        );
    }

    /**
     * @param mixed $value
     * @param string[] $search
     * @param string[] $replace
     * @param array<string,true> $classes
     * @param array<non-empty-string,string> $constants
     */
    private static function doCode(
        $value,
        string $delimiter,
        string $arrow,
        ?string $escapeCharacters,
        ?string $escapeRegex,
        array $search,
        array $replace,
        string $tab,
        array $classes,
        array $constants,
        ?string $regex,
        bool $multiline,
        string $eol,
        string $indent = ''
    ): string {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value)) {
            if ($classes && isset($classes[$value])) {
                return $value . '::class';
            }

            if ($regex !== null) {
                $parts = [];
                while (Pcre::match($regex, $value, $matches, \PREG_OFFSET_CAPTURE)) {
                    if ($matches[0][1] > 0) {
                        $parts[] = substr($value, 0, $matches[0][1]);
                    }
                    $parts[] = $matches[0][0];
                    $value = substr($value, $matches[0][1] + strlen($matches[0][0]));
                }
                if ($parts) {
                    if ($value !== '') {
                        $parts[] = $value;
                    }
                    foreach ($parts as &$part) {
                        $part = $constants[$part]
                            ?? self::doCode($part, $delimiter, $arrow, $escapeCharacters, $escapeRegex, $search, $replace, $tab, $classes, [], null, $multiline, $eol, $indent);
                    }
                    return implode(' . ', $parts);
                }
            }

            if ($multiline) {
                $escape = '';
                $match = '';
            } else {
                $escape = "\n\r";
                $match = '\n\r';
            }

            // Don't escape UTF-8 leading bytes (\xc2 -> \xf4) or continuation
            // bytes (\x80 -> \xbf)
            if (mb_check_encoding($value, 'UTF-8')) {
                $escape .= "\x7f\xc0\xc1\xf5..\xff";
                $match .= '\x7f\xc0\xc1\xf5-\xff';
                $utf8 = true;
            } else {
                $escape .= "\x7f..\xff";
                $match .= '\x7f-\xff';
                $utf8 = false;
            }

            // Escape strings that contain characters in `$escape` or
            // `$escapeCharacters`
            if (Pcre::match("/[\\x00-\\x09\\x0b\\x0c\\x0e-\\x1f{$match}{$escapeRegex}]/", $value)) {
                // \0..\t\v\f\x0e..\x1f = \0..\x1f without \n and \r
                $escaped = addcslashes(
                    $value,
                    "\0..\t\v\f\x0e..\x1f\"\$\\" . $escape . $escapeCharacters
                );

                // Convert blank/ignorable code points to "\u{xxxx}" unless they
                // belong to a recognised Unicode sequence
                if ($utf8) {
                    $escaped = Pcre::replaceCallback(
                        '/(?![\x00-\x7f])\X/u',
                        fn(array $matches): string =>
                            Pcre::match('/^' . Regex::INVISIBLE_CHAR . '$/u', $matches[0])
                                ? sprintf('\u{%04X}', mb_ord($matches[0]))
                                : $matches[0],
                        $escaped,
                    );
                }

                // Replace characters in `$escapeCharacters` with the equivalent
                // hexadecimal escape
                if ($search) {
                    $escaped = Pcre::replace($search, $replace, $escaped);
                }

                // Convert octal notation to hex (e.g. "\177" to "\x7f") and
                // correct for differences between C and PHP escape sequences:
                // - recognised by PHP: \0 \e \f \n \r \t \v
                // - applied by addcslashes: \000 \033 \a \b \f \n \r \t \v
                $escaped = Pcre::replaceCallback(
                    '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?<NUL>000(?![0-7]))|(?<octal>[0-7]{3})|(?<cslash>[ab]))/',
                    fn(array $matches): string =>
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

                // Remove unnecessary backslashes
                $escaped = Pcre::replace(
                    '/(?<!\\\\)\\\\\\\\(?![nrtvef\\\\$"]|[0-7]|x[0-9a-fA-F]|u\{[0-9a-fA-F]+\}|$)/',
                    '\\',
                    $escaped
                );

                return '"' . $escaped . '"';
            }
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
        if (!$isList) {
            $isMixedList = false;
            $keys = 0;
            foreach (array_keys($value) as $key) {
                if (!is_int($key)) {
                    continue;
                }
                if ($keys++ !== $key) {
                    $isMixedList = false;
                    break;
                }
                $isMixedList = true;
            }
        }
        foreach ($value as $key => $value) {
            $value = self::doCode($value, $delimiter, $arrow, $escapeCharacters, $escapeRegex, $search, $replace, $tab, $classes, $constants, $regex, $multiline, $eol, $indent);
            if ($isList || ($isMixedList && is_int($key))) {
                $values[] = $value;
                continue;
            }
            $key = self::doCode($key, $delimiter, $arrow, $escapeCharacters, $escapeRegex, $search, $replace, $tab, $classes, $constants, $regex, $multiline, $eol, $indent);
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
     * @template T
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
            foreach ($var as $key => $value) {
                $array[$key] = self::doCopy($value, $skip, $flags, $map);
            }
            /** @var T */
            return $array ?? [];
        }

        if (!is_object($var) || $var instanceof UnitEnum) {
            return $var;
        }

        $id = spl_object_id($var);
        if (isset($map[$id])) {
            /** @var T */
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
