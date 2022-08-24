<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IConvertibleEnumeration;
use Lkrms\Util\Convert;
use ReflectionClass;
use ReflectionClassConstant;
use RuntimeException;
use UnexpectedValueException;

/**
 * Base class for enumerations that use reflection to convert the integer values
 * of their public constants to and from their names
 */
abstract class ConvertibleEnumeration extends Enumeration implements IConvertibleEnumeration
{
    /**
     * Class names => [ lower-case constant names => values ]
     *
     * @var array<string,array<string,int>>
     */
    private static $ValueMaps = [];

    /**
     * Class names => [ constant values => names ]
     *
     * @var array<string,array<int,string>>
     */
    private static $NameMaps = [];

    private static function getMap(bool $flipped = false): array
    {
        $constants = (new ReflectionClass(static::class))->getConstants(
            ReflectionClassConstant::IS_PUBLIC
        );
        $map = self::$ValueMaps[static::class] = array_combine(array_map(
            fn($key) => strtolower($key),
            array_keys($constants)
        ), $constants);
        $flippedMap = self::$NameMaps[static::class] = array_flip($constants);
        if (count($constants) != count($flippedMap))
        {
            throw new RuntimeException("Public constants are not unique: " . static::class);
        }
        return $flipped ? $flippedMap : $map;
    }

    final public static function fromName(string $name): int
    {
        if (is_null($value = (self::$ValueMaps[static::class] ?? self::getMap())[strtolower($name)] ?? null))
        {
            throw new UnexpectedValueException("Invalid "
                . Convert::classToBasename(static::class) . " name: $name");
        }
        return $value;
    }

    final public static function toName(int $value): string
    {
        if (is_null($name = (self::$NameMaps[static::class] ?? self::getMap(true))[$value] ?? null))
        {
            throw new UnexpectedValueException("Invalid "
                . Convert::classToBasename(static::class) . ": $value");
        }
        return $name;
    }
}
