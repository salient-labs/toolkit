<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Has public constants with unique values of a given type
 *
 * @template TValue
 */
interface IEnumeration
{
    /**
     * Get an array that maps constant names to values
     *
     * @return array<string,TValue>
     */
    public static function cases(): array;
}
