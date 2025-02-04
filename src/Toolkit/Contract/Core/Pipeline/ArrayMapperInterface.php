<?php declare(strict_types=1);

namespace Salient\Contract\Core\Pipeline;

/**
 * @api
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
     * `null` is added to the output array if the input array has no data for a
     * given map.
     */
    public const ADD_MISSING = 4;

    /**
     * Throw an exception if there are missing values
     *
     * An {@InvalidArgumentException} is thrown if the input array has no data
     * for a given map.
     */
    public const REQUIRE_MAPPED = 8;

    /**
     * Map an input array to an output array
     *
     * @param mixed[] $in
     * @return mixed[]
     */
    public function map(array $in): array;
}
