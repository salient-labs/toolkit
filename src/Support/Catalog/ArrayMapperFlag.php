<?php declare(strict_types=1);

namespace Lkrms\Support\Catalog;

use Lkrms\Concept\Enumeration;

/**
 * ArrayMapper flags
 *
 * By default, {@see ArrayMapper} closures:
 * - populate the output array with values mapped from the input array
 * - ignore any missing values (maps for which there are no input values)
 * - discard unmapped values (input values for which there are no maps)
 *
 * To override these defaults, use one or more {@see ArrayMapperFlag} constants
 * to create a bitmask.
 *
 * @extends Enumeration<int>
 */
final class ArrayMapperFlag extends Enumeration
{
    /**
     * Remove null values from the output array
     */
    public const REMOVE_NULL = 1;

    /**
     * Add unmapped values to the output array
     */
    public const ADD_UNMAPPED = 2;

    /**
     * Add missing values to the output array
     *
     * If set, `null` will be added to the output array if the input array has
     * no data for a given map.
     */
    public const ADD_MISSING = 4;

    /**
     * Throw an exception if there are missing values
     *
     * If set and the input array has no data for a given map, an
     * `UnexpectedValueException` will be thrown.
     */
    public const REQUIRE_MAPPED = 8;
}
