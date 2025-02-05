<?php declare(strict_types=1);

namespace Salient\Contract\Catalog;

/**
 * @api
 */
interface ListConformity
{
    /**
     * Arrays may have different keys in different orders
     */
    public const NONE = 0;

    /**
     * Data arrays always have the same keys in the same order, but the given
     * key map may have different keys in a different order
     */
    public const PARTIAL = 1;

    /**
     * Data arrays always have the same keys in the same order as each other and
     * the given key map
     */
    public const COMPLETE = 2;
}
