<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Contract\IEnumeration;

/**
 * Base class for enumerations
 *
 * @template TValue
 *
 * @extends Catalog<TValue>
 * @implements IEnumeration<TValue>
 */
abstract class Enumeration extends Catalog implements IEnumeration
{
    /**
     * @inheritDoc
     */
    public static function cases(): array
    {
        return self::constants();
    }

    /**
     * @inheritDoc
     */
    public static function hasValue($value): bool
    {
        if (
            (is_int($value) || is_string($value)) &&
            isset(self::constantNames()[$value])
        ) {
            return true;
        }
        return in_array($value, self::constants(), true);
    }
}
