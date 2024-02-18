<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\IsConvertibleEnumeration;
use Lkrms\Contract\IConvertibleEnumeration;
use Salient\Core\Utility\Str;
use LogicException;

/**
 * Base class for enumerations that use static arrays to map constants to and
 * from their names
 *
 * @template TValue of array-key
 *
 * @extends Enumeration<TValue>
 * @implements IConvertibleEnumeration<TValue>
 */
abstract class ConvertibleEnumeration extends Enumeration implements IConvertibleEnumeration
{
    /** @use IsConvertibleEnumeration<TValue> */
    use IsConvertibleEnumeration;

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

    /**
     * @inheritDoc
     */
    final public static function fromName(string $name)
    {
        $value = static::$ValueMap[$name]
            ?? static::$ValueMap[Str::upper($name)]
            ?? null;
        if ($value === null) {
            throw new LogicException(
                sprintf('Argument #1 ($name) is invalid: %s', $name)
            );
        }
        return $value;
    }

    /**
     * @inheritDoc
     */
    final public static function toName($value): string
    {
        $name = static::$NameMap[$value] ?? null;
        if ($name === null) {
            throw new LogicException(
                sprintf('Argument #1 ($value) is invalid: %s', $value)
            );
        }
        return $name;
    }

    /**
     * @inheritDoc
     */
    final public static function cases(): array
    {
        return static::$ValueMap;
    }

    /**
     * @inheritDoc
     */
    final public static function hasValue($value): bool
    {
        return isset(static::$NameMap[$value]);
    }
}
