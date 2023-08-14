<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Converts the values of public constants to and from their names
 *
 * @template TValue
 *
 * @extends IEnumeration<TValue>
 */
interface IConvertibleEnumeration extends IEnumeration
{
    /**
     * Get the value of a constant from its name
     *
     * @return TValue
     */
    public static function fromName(string $name);

    /**
     * Get the name of a constant from its value
     *
     * @param TValue $value
     */
    public static function toName($value): string;
}
