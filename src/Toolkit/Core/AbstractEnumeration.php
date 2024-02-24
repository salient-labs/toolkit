<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Core\Contract\EnumerationInterface;

/**
 * Base class for enumerations
 *
 * @api
 *
 * @template TValue
 *
 * @extends AbstractCatalog<TValue>
 * @implements EnumerationInterface<TValue>
 */
abstract class AbstractEnumeration extends AbstractCatalog implements EnumerationInterface
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
