<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Converts the integer values of its public constants to and from their names
 *
 */
interface IConvertibleEnumeration extends IEnumeration
{
    /**
     * Get the value of a constant from its name
     *
     */
    public static function fromName(string $name): int;

    /**
     * Get the name of a constant from its value
     *
     */
    public static function toName(int $value): string;
}
