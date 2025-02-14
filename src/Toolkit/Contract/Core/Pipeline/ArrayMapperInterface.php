<?php declare(strict_types=1);

namespace Salient\Contract\Core\Pipeline;

use Salient\Contract\Catalog\HasConformity;
use Salient\Contract\Core\Exception\InvalidDataException;

/**
 * @api
 */
interface ArrayMapperInterface extends HasConformity
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
     */
    public const REQUIRE_MAPPED = 8;

    /**
     * Map an input array to an output array
     *
     * @param mixed[] $in
     * @return mixed[]
     * @throws InvalidDataException if {@see REQUIRE_MAPPED} is applied and
     * `$in` has no data for a given map.
     */
    public function map(array $in): array;
}
