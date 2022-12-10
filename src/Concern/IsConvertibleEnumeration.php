<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Facade\Convert;
use UnexpectedValueException;

/**
 * Implements IConvertibleEnumeration to convert the integer values of public
 * constants to and from their names
 *
 * An alternative to {@see \Lkrms\Concept\ConvertibleEnumeration}, which uses
 * reflection.
 */
trait IsConvertibleEnumeration
{
    /**
     * Return an array that maps values to names
     *
     * @return array<int,string> Value => name
     */
    abstract protected static function getNameMap(): array;

    /**
     * Return an array that maps names to values
     *
     * Array keys must be lowercase.
     *
     * @return array<string,int> Lowercase name => value
     */
    abstract protected static function getValueMap(): array;

    public static function fromName(string $name): int
    {
        if (is_null($value = static::getValueMap()[strtolower($name)] ?? null)) {
            throw new UnexpectedValueException('Invalid ' . Convert::classToBasename(static::class) . " name: $name");
        }

        return $value;
    }

    public static function toName(int $value): string
    {
        if (is_null($name = static::getNameMap()[$value] ?? null)) {
            throw new UnexpectedValueException('Invalid ' . Convert::classToBasename(static::class) . ": $value");
        }

        return $name;
    }
}
