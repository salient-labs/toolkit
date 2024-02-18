<?php declare(strict_types=1);

namespace Salient\Core\Contract;

/**
 * Has public constants with values of a given type
 *
 * @template TValue
 */
interface DictionaryInterface
{
    /**
     * Get an array that maps constant names to values
     *
     * @return array<string,TValue>
     */
    public static function definitions(): array;
}
