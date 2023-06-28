<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IConvertibleEnumeration;
use LogicException;

/**
 * Uses static arrays to convert public constants to and from their names
 *
 * @template TValue
 *
 * @extends Enumeration<TValue>
 * @implements IConvertibleEnumeration<TValue>
 *
 */
abstract class ConvertibleEnumeration extends Enumeration implements IConvertibleEnumeration
{
    /**
     * An array that maps values to names
     *
     * @var array<TValue,string>
     */
    protected static $NameMap = [];

    /**
     * An array that maps UPPERCASE NAMES to values
     *
     * @var array<string,TValue>
     */
    protected static $ValueMap = [];

    final public static function fromName(string $name)
    {
        if (($value = static::$ValueMap[$name]
                ?? static::$ValueMap[strtoupper($name)]
                ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($name) is invalid: %s', $name)
            );
        }
        return $value;
    }

    final public static function toName($value): string
    {
        if (($name = static::$NameMap[$value] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($value) is invalid: %d', $value)
            );
        }
        return $name;
    }

    final public static function cases(): array
    {
        return static::$ValueMap;
    }

    /**
     * Get the values of constants from their names
     *
     * @param string[] $names
     * @return TValue[]
     */
    final public static function fromNames(array $names): array
    {
        return array_map(
            fn(string $name) => self::fromName($name),
            $names
        );
    }

    /**
     * Get the names of constants from their values
     *
     * @param TValue[] $values
     * @return string[]
     */
    final public static function toNames(array $values): array
    {
        return array_map(
            fn($value): string => self::toName($value),
            $values
        );
    }
}
