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
