<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Has public constants with unique values of a given type, and maps them to and
 * from their names
 *
 * @api
 *
 * @template TValue
 *
 * @extends EnumerationInterface<TValue>
 */
interface ConvertibleEnumerationInterface extends EnumerationInterface
{
    /**
     * Get the value of a public constant from its name
     *
     * @return TValue
     */
    public static function fromName(string $name);

    /**
     * Get the values of public constants from their names
     *
     * @param string[] $index
     * @return TValue[]
     */
    public static function fromNames(array $index): array;

    /**
     * Get the name of a public constant from its value
     *
     * @param TValue $value
     */
    public static function toName($value): string;

    /**
     * Get the names of public constants from their values
     *
     * @param TValue[] $values
     * @return string[]
     */
    public static function toNames(array $values): array;
}
