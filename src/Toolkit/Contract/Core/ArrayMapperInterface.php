<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * Array mapper flags
 */
interface ArrayMapperInterface
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
     * If applied, `null` is added to the output array if the input array has no
     * data for a given map.
     */
    public const ADD_MISSING = 4;

    /**
     * Throw an exception if there are missing values
     *
     * If applied and the input array has no data for a given map, an
     * `UnexpectedValueException` is thrown.
     */
    public const REQUIRE_MAPPED = 8;
}
