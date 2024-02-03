<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Container\Contract\ServiceSingletonInterface;
use Lkrms\Container\Contract\SingletonInterface;
use Lkrms\Contract\Arrayable;
use Lkrms\Exception\UncloneableObjectException;
use Lkrms\Utility\Catalog\CopyFlag;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use DateTimeInterface;
use DateTimeZone;
use ReflectionClass;
use ReflectionObject;
use UnitEnum;

/**
 * Get values from other values
 */
final class Get extends Utility
{
    /**
     * If a value is callable, get its return value
     *
     * @template T
     *
     * @param (callable(mixed...): T)|T $value
     * @param mixed ...$args Passed to `$value` if it is callable.
     * @return T
     */
    public static function value($value, ...$args)
    {
        if (is_callable($value)) {
            return $value(...$args);
        }
        return $value;
    }

    /**
     * Get the first value that is not null, or return the last value
     *
     * @template T
     *
     * @param T|null $value
     * @param T|null ...$values
     * @return T|null
     */
    public static function coalesce($value, ...$values)
    {
        array_unshift($values, $value);
        $last = array_pop($values);
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            return $value;
        }
        return $last;
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
        /** @var array<string,true> */
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
                (string) $escapeCharacters !== '' &&
                strpbrk($value, $escapeCharacters) !== false
            )
        )) {
            $escaped = addcslashes($value, "\0..\x1f\$\\" . $escapeCharacters);

            // Replace characters in `$escapeCharacters` with the equivalent
            // hexadecimal escape
            if ((string) $escapeCharacters !== '') {
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
            !($flags & CopyFlag::COPY_SINGLETONS) && (
                $var instanceof SingletonInterface ||
                $var instanceof ServiceSingletonInterface
            )
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
