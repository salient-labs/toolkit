<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Has public constants with unique values of a given type
 *
 * @api
 *
 * @template TValue
 */
interface EnumerationInterface
{
    /**
     * Get an array that maps public constant names to values
     *
     * @return array<string,TValue>
     */
    public static function cases(): array;

    /**
     * Check if the class has a public constant with the given value
     *
     * @param TValue $value
     */
    public static function hasValue($value): bool;
}
