<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Facade\Convert;
use UnexpectedValueException;

/**
 * Implements IConvertibleEnumeration to convert the integer values of public
 * constants to and from their names
 *
 * Static properties `$NameMap` and `$ValueMap` must be declared when using this
 * trait, and array keys in `$ValueMap` must be lowercase.
 *
 * @property-read array<int,string> $NameMap Value => name
 * @property-read array<string,int> $ValueMap Lowercase name => value
 */
trait IsConvertibleEnumeration
{
    public static function fromName(string $name): int
    {
        if (is_null($value = static::$ValueMap[strtolower($name)] ?? null))
        {
            throw new UnexpectedValueException("Invalid " . Convert::classToBasename(static::class) . " name: $name");
        }

        return $value;
    }

    public static function toName(int $value): string
    {
        if (is_null($name = static::$NameMap[$value] ?? null))
        {
            throw new UnexpectedValueException("Invalid " . Convert::classToBasename(static::class) . ": $value");
        }

        return $name;
    }

}
