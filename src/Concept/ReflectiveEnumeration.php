<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IConvertibleEnumeration;
use LogicException;
use ReflectionClass;

/**
 * Uses reflection to convert public constants to and from their names
 *
 * @template TValue
 *
 * @extends Enumeration<TValue>
 * @implements IConvertibleEnumeration<TValue>
 */
abstract class ReflectiveEnumeration extends Enumeration implements IConvertibleEnumeration
{
    /**
     * Class name => [ constant value => name ]
     *
     * @var array<string,array<TValue,string>>
     */
    private static $NameMaps = [];

    /**
     * Class name => [ constant name => value ]
     *
     * @var array<string,array<string,TValue>>
     */
    private static $ValueMaps = [];

    private static function loadMaps(): void
    {
        $constants = (new ReflectionClass(static::class))->getReflectionConstants();
        $valueMap = [];
        $nameMap = [];
        foreach ($constants as $constant) {
            if (!$constant->isPublic()) {
                continue;
            }
            $name = $constant->getName();
            $value = $constant->getValue();
            $valueMap[$name] = $value;
            $nameMap[$value] = $name;
        }
        if (!$valueMap) {
            self::$ValueMaps[static::class] = [];
            self::$NameMaps[static::class] = [];
            return;
        }
        if (count($valueMap) !== count($nameMap)) {
            throw new LogicException(
                sprintf('Public constants are not unique: %s', static::class)
            );
        }
        // Add UPPER_CASE names to $valueMap if necessary
        $valueMap += array_combine(array_map('strtoupper', array_keys($valueMap)), $valueMap);
        self::$ValueMaps[static::class] = $valueMap;
        self::$NameMaps[static::class] = $nameMap;
    }

    final public static function fromName(string $name)
    {
        if (!isset(self::$ValueMaps[static::class])) {
            self::loadMaps();
        }
        if (($value = self::$ValueMaps[static::class][$name]
                ?? self::$ValueMaps[static::class][strtoupper($name)]
                ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($name) is invalid: %s', $name)
            );
        }
        return $value;
    }

    final public static function toName($value): string
    {
        if (!isset(self::$NameMaps[static::class])) {
            self::loadMaps();
        }
        if (($name = self::$NameMaps[static::class][$value] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($value) is invalid: %d', $value)
            );
        }
        return $name;
    }

    final public static function cases(): array
    {
        if (!isset(self::$ValueMaps[static::class])) {
            self::loadMaps();
        }
        return self::$ValueMaps[static::class];
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
