<?php declare(strict_types=1);

namespace Salient\Contract\Catalog;

/**
 * @api
 */
interface HasConformity
{
    /**
     * Arrays may have different keys in different orders
     */
    public const CONFORMITY_NONE = 0;

    /**
     * Data arrays always have the same keys in the same order, but the given
     * key map may have different keys in a different order
     */
    public const CONFORMITY_PARTIAL = 1;

    /**
     * Data arrays always have the same keys in the same order as each other and
     * the given key map
     */
    public const CONFORMITY_COMPLETE = 2;
}
