<?php

declare(strict_types=1);

namespace Lkrms\Core\Contract;

/**
 * Converts the integer values of its public constants to and from their names
 *
 */
interface IConvertibleEnumeration extends IEnumeration
{
    /**
     * Get the value of a constant from its name
     *
     * @param string $name
     * @return int
     */
    public static function fromName(string $name): int;

    /**
     * Get the name of a constant from its value
     *
     * @param int $value
     * @return string
     */
    public static function toName(int $value): string;
}
