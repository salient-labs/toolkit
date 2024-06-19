<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Has public constants with values of a given type
 *
 * @api
 *
 * @template TValue
 */
interface DictionaryInterface
{
    /**
     * Get an array that maps public constant names to values
     *
     * @return array<string,TValue>
     */
    public static function definitions(): array;
}
