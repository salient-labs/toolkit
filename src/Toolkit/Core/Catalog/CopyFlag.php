<?php declare(strict_types=1);

namespace Salient\Core\Catalog;

use Salient\Core\Utility\Get;
use Salient\Core\AbstractEnumeration;

/**
 * Get::copy() flags
 *
 * @api
 *
 * @extends AbstractEnumeration<int>
 */
final class CopyFlag extends AbstractEnumeration
{
    /**
     * Do not throw an exception if an uncloneable object is encountered
     */
    public const SKIP_UNCLONEABLE = 1;

    /**
     * Assign values to properties by reference
     *
     * Required if an object graph contains nodes with properties passed or
     * assigned by reference.
     */
    public const ASSIGN_PROPERTIES_BY_REFERENCE = 2;

    /**
     * Take a shallow copy of objects with a __clone method
     */
    public const TRUST_CLONE_METHODS = 4;

    /**
     * Copy service containers
     */
    public const COPY_CONTAINERS = 8;

    /**
     * Copy singletons
     */
    public const COPY_SINGLETONS = 16;
}
