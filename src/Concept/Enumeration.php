<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Concern\IsCatalog;
use Lkrms\Contract\IEnumeration;

/**
 * Base class for enumerations
 *
 * @template TValue
 *
 * @implements IEnumeration<TValue>
 */
abstract class Enumeration implements IEnumeration
{
    /** @use IsCatalog<TValue> */
    use IsCatalog;

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
