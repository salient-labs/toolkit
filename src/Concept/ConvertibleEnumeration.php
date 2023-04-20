<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\IsConvertibleEnumeration;
use Lkrms\Contract\IConvertibleEnumeration;
use LogicException;
use ReflectionClass;

/**
 * Uses reflection to convert the class's public constants to and from their
 * names
 *
 * @see IsConvertibleEnumeration A trait that provides an alternative
 * implementation.
 */
abstract class ConvertibleEnumeration extends Enumeration implements IConvertibleEnumeration
{
    /**
     * Class name => [ constant name => value ]
     *
     * @var array<string,array<string,int>>
     */
    private static $ValueMaps = [];

    /**
     * Class name => [ constant value => name ]
     *
     * @var array<string,array<int,string>>
     */
    private static $NameMaps = [];

    private static function loadMaps(): void
    {
        $constants =
            (new ReflectionClass(static::class))
                ->getReflectionConstants();

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
        if (count($valueMap) != count($nameMap)) {
            throw new LogicException(
                sprintf('Public constants are not unique: %s', static::class)
            );
        }
        // Add UPPER_CASE names to $valueMap if necessary
        $valueMap += array_combine(array_map('strtoupper', array_keys($valueMap)), $valueMap);
        self::$ValueMaps[static::class] = $valueMap;
        self::$NameMaps[static::class] = $nameMap;
    }

    final public static function fromName(string $name): int
    {
        if (($map = self::$ValueMaps[static::class] ?? null) === null) {
            self::loadMaps();
            $map = self::$ValueMaps[static::class];
        }
        if (($value = $map[$name] ?? $map[strtoupper($name)] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($name) is invalid: %s', $name)
            );
        }

        return $value;
    }

    final public static function toName(int $value): string
    {
        if ((self::$NameMaps[static::class] ?? null) === null) {
            self::loadMaps();
        }
        if (($name = self::$NameMaps[static::class][$value] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($value) is invalid: %d', $value)
            );
        }

        return $name;
    }
}
