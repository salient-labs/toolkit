<?php

declare(strict_types=1);

namespace Lkrms\Template;

use Lkrms\Reflect\PropertyResolver;
use UnexpectedValueException;

/**
 * Implements IConstructible to convert arrays to instances
 *
 * @package Lkrms
 */
trait TConstructible
{
    /**
     * Create a new class instance from an array
     *
     * The constructor (if any) is invoked with parameters taken from `$array`.
     * If `$array` values remain, they are assigned to public properties. If
     * further values remain and the class implements {@see IExtensible}, they
     * are assigned via {@see IExtensible::setMetaProperty()}.
     *
     * Array keys, constructor parameters and public property names are
     * normalised for comparison.
     *
     * @param array $array
     * @return static
     * @throws UnexpectedValueException when required values are not provided
     */
    public static function from(array $array)
    {
        return (PropertyResolver::getFor(static::class)->getCreateFromClosure())($array);
    }

    /**
     * Convert a list of arrays to a list of instances
     *
     * To suppress array signature checks, set `$sameKeys` to `true` if every
     * array in the list has the same keys in the same order.
     *
     * @param array[] $arrays
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * array in the list has the same keys in the same order.
     * @return static[]
     */
    public static function listFrom(array $arrays, bool $sameKeys = false): array
    {
        if (empty($arrays))
        {
            return [];
        }

        if ($sameKeys)
        {
            $closure = PropertyResolver::getFor(static::class)->getCreateFromSignatureClosure(array_keys(reset($arrays)));
        }
        else
        {
            $closure = PropertyResolver::getFor(static::class)->getCreateFromClosure();
        }

        $list = [];

        foreach ($arrays as $index => $array)
        {
            if (!is_array($array))
            {
                throw new UnexpectedValueException("Array expected at index $index");
            }

            $list[] = ($closure)($array);
        }

        return $list;
    }
}

