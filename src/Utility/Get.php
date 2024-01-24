<?php declare(strict_types=1);

namespace Lkrms\Utility;

use Lkrms\Concept\Utility;
use Lkrms\Contract\Arrayable;
use Lkrms\Contract\IServiceShared;
use Lkrms\Contract\IServiceSingleton;
use Lkrms\Exception\UncloneableObjectException;
use Lkrms\Utility\Catalog\CopyFlag;
use Psr\Container\ContainerInterface;
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
     * Get the first value that is not null
     *
     * @param mixed ...$values
     * @return mixed
     */
    public static function coalesce(...$values)
    {
        while ($values) {
            $value = array_shift($values);
            if ($value !== null) {
                return $value;
            }
        }
        return null;
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
     * Convert php.ini values like "128M" to bytes
     *
     * @param string $size From the PHP FAQ: "The available options are K (for
     * Kilobytes), M (for Megabytes) and G (for Gigabytes), and are all
     * case-insensitive."
     */
    public static function sizeToBytes(string $size): int
    {
        if (!Pcre::match('/^(.+?)([KMG]?)$/', Str::upper($size), $match) || !is_numeric($match[1])) {
            throw new LogicException("Invalid shorthand: '$size'");
        }

        $power = ['' => 0, 'K' => 1, 'M' => 2, 'G' => 3];

        return (int) ($match[1] * (1024 ** $power[$match[2]]));
    }

    /**
     * Like var_export but with more compact output
     *
     * Indentation is applied automatically if `$delimiter` contains one or more
     * newline characters.
     *
     * Array keys are suppressed for list arrays.
     *
     * @param mixed $value
     * @param string $delimiter Added between array elements.
     * @param string $arrow Added between array keys and values.
     * @param string|null $escapeCharacters Characters to escape in hexadecimal
     * notation.
     */
    public static function valueToCode(
        $value,
        string $delimiter = ', ',
        string $arrow = ' => ',
        ?string $escapeCharacters = null,
        string $tab = '    '
    ): string {
        $eol = Get::eol($delimiter) ?: '';
        $multiline = (bool) $eol;
        return self::doValueToCode(
            $value,
            $delimiter,
            $arrow,
            $escapeCharacters,
            $tab,
            $multiline,
            $eol,
        );
    }

    /**
     * @param mixed $value
     */
    private static function doValueToCode(
        $value,
        string $delimiter,
        string $arrow,
        ?string $escapeCharacters,
        string $tab,
        bool $multiline,
        string $eol,
        string $indent = ''
    ): string {
        if ($value === null) {
            return 'null';
        }

        if (is_string($value) &&
            (Pcre::match('/\v/', $value) ||
                ($escapeCharacters !== null &&
                    strpbrk($value, $escapeCharacters) !== false))) {
            $characters = "\0..\x1f\$\\" . $escapeCharacters;
            $escaped = addcslashes($value, $characters);

            // Escape explicitly requested characters in hexadecimal notation
            if ($escapeCharacters !== null) {
                $search = [];
                $replace = [];
                foreach (str_split($escapeCharacters) as $character) {
                    $search[] = sprintf('/((?<!\\\\)(?:\\\\\\\\)*)%s/', preg_quote(addcslashes($character, $character), '/'));
                    $replace[] = sprintf('$1\x%02x', ord($character));
                }
                $escaped = Pcre::replace($search, $replace, $escaped);
            }

            // Convert octal notation to hexadecimal and correct for differences
            // between C and PHP escape sequences:
            // - recognised by PHP: \0 \e \f \n \r \t \v
            // - returned by addcslashes: \000 \033 \a \b \f \n \r \t \v
            Pcre::replaceCallback(
                '/((?<!\\\\)(?:\\\\\\\\)*)\\\\(?:(?<octal>[0-7]{3})|(?<cslash>[ab]))/',
                fn(array $matches) =>
                    $matches[1]
                    . ($matches['octal'] !== null
                        ? (($dec = octdec($matches['octal']))
                            ? ($dec === 27 ? '\e' : sprintf('\x%02x', $dec))
                            : '\0')
                        : sprintf('\x%02x', ['a' => 7, 'b' => 8][$matches['cslash']])),
                $escaped,
                -1,
                $count,
                \PREG_UNMATCHED_AS_NULL,
            );

            return '"' . $escaped . '"';
        }

        if (!is_array($value)) {
            return var_export($value, true);
        }

        if (!$value) {
            return '[]';
        }

        $prefix = '[';
        $suffix = ']';
        $glue = $delimiter;

        if ($multiline) {
            $suffix = "{$delimiter}{$indent}{$suffix}";
            $indent .= $tab;
            $prefix .= "{$eol}{$indent}";
            $glue .= $indent;
        }

        if (Arr::isList($value)) {
            foreach ($value as $value) {
                $values[] = self::doValueToCode(
                    $value,
                    $delimiter,
                    $arrow,
                    $escapeCharacters,
                    $tab,
                    $multiline,
                    $eol,
                    $indent,
                );
            }
        } else {
            foreach ($value as $key => $value) {
                $values[] = self::doValueToCode(
                    $key,
                    $delimiter,
                    $arrow,
                    $escapeCharacters,
                    $tab,
                    $multiline,
                    $eol,
                    $indent,
                ) . $arrow . self::doValueToCode(
                    $value,
                    $delimiter,
                    $arrow,
                    $escapeCharacters,
                    $tab,
                    $multiline,
                    $eol,
                    $indent,
                );
            }
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
            $var instanceof ContainerInterface
        ) || (
            !($flags & CopyFlag::COPY_SINGLETONS) && (
                $var instanceof IServiceSingleton ||
                $var instanceof IServiceShared
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
