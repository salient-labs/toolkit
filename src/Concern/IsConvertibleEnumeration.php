<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Concept\ConvertibleEnumeration;
use Lkrms\Contract\IConvertibleEnumeration;
use LogicException;

/**
 * Uses arrays provided by the class to map its public constants to and from
 * their names
 *
 * @see IConvertibleEnumeration Implemented by this trait.
 * @see ConvertibleEnumeration An abstract class that provides an alternative
 * implementation using reflection.
 *
 * @psalm-require-implements IConvertibleEnumeration
 */
trait IsConvertibleEnumeration
{
    /**
     * Get an array that maps values to names
     *
     * @return array<int,string> Value => name
     */
    abstract protected static function getNameMap(): array;

    /**
     * Get an array that maps names to values
     *
     * @return array<string,int> Lowercase name => value
     */
    abstract protected static function getValueMap(): array;

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

    public static function fromName(string $name): int
    {
        if (($map = self::$ValueMaps[static::class] ?? null) === null) {
            // Add UPPER_CASE names to the map if necessary
            $map = static::getValueMap();
            $map += array_combine(array_map('strtoupper', array_keys($map)), $map);
            self::$ValueMaps[static::class] = $map;
        }
        if (($value = $map[$name] ?? $map[strtoupper($name)] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($name) is invalid: %s', $name)
            );
        }

        return $value;
    }

    public static function toName(int $value): string
    {
        if ((self::$NameMaps[static::class] ?? null) === null) {
            self::$NameMaps[static::class] = static::getNameMap();
        }
        if (($name = self::$NameMaps[static::class][$value] ?? null) === null) {
            throw new LogicException(
                sprintf('Argument #1 ($value) is invalid: %d', $value)
            );
        }

        return $name;
    }
}
