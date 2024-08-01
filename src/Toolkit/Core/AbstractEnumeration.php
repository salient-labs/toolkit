<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Core\EnumerationInterface;
use Salient\Utility\Reflect;

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
        return Reflect::hasConstantWithValue(static::class, $value);
    }
}
