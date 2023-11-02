<?php declare(strict_types=1);

namespace Lkrms\Sync\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Sync\Exception\SyncFilterPolicyViolationException;

/**
 * Policies for unclaimed sync operation filter values
 *
 * @extends Enumeration<int>
 */
final class FilterPolicy extends Enumeration
{
    /**
     * Ignore unclaimed filter values
     *
     * The provider returns unfiltered entities, all of which are returned to
     * the caller.
     */
    public const IGNORE = 0;

    /**
     * Throw an exception if there are unclaimed filter values
     *
     * A {@see SyncFilterPolicyViolationException} is thrown and the request is
     * not passed to the provider.
     *
     * This is the default policy.
     */
    public const THROW_EXCEPTION = 1;

    /**
     * Return an empty result if there are unclaimed filter values
     *
     * The request is not passed to the provider. An empty array (`[]`) is
     * returned to the caller for {@see SyncOperation::CREATE_LIST},
     * {@see SyncOperation::READ_LIST}, {@see SyncOperation::UPDATE_LIST} and
     * {@see SyncOperation::DELETE_LIST}, otherwise `null` is returned.
     */
    public const RETURN_EMPTY = 2;

    /**
     * Perform local filtering of entities returned by the provider
     *
     * The provider returns unfiltered entities, and any that don't match the
     * unclaimed filter values are removed from the result returned to the
     * caller.
     */
    public const FILTER_LOCALLY = 3;
}
