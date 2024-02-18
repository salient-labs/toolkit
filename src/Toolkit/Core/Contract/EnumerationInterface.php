<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Has public constants with unique values of a given type
 *
 * @template TValue
 */
interface EnumerationInterface
{
    /**
     * Get an array that maps constant names to values
     *
     * @return array<string,TValue>
     */
    public static function cases(): array;

    /**
     * True if the class has a public constant with the given value
     *
     * @param TValue $value
     */
    public static function hasValue($value): bool;
}
