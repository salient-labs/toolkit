<?php declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Extends ConvertibleEnumeration and ReflectiveEnumeration
 *
 * @template TValue of array-key
 */
trait IsConvertibleEnumeration
{
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
