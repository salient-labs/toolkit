<?php declare(strict_types=1);

namespace Lkrms\Sync\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Sync\Support\DeferredSyncEntity;
use Lkrms\Sync\Support\SyncStore;

/**
 * Policies for sync entity deferral
 *
 * @extends Enumeration<int>
 */
final class DeferredSyncEntityPolicy extends Enumeration
{
    /**
     * Do not resolve deferred entities
     *
     * If {@see SyncStore::resolveDeferredEntities()} is not called manually,
     * unresolved {@see DeferredSyncEntity} instances may appear in object
     * graphs returned by sync operations.
     */
    public const DO_NOT_RESOLVE = 0;

    /**
     * Resolve deferred entities immediately
     *
     * This is the least efficient policy because it produces the most round
     * trips, but it does guarantee the return of fully resolved object graphs.
     */
    public const RESOLVE_EARLY = 1;

    /**
     * Resolve deferred entities after reaching the end of each stream of entity
     * data
     *
     * This policy minimises the number of round trips to the backend, but
     * unresolved {@see DeferredSyncEntity} instances may appear in object
     * graphs until they have been fully traversed.
     */
    public const RESOLVE_LATE = 2;
}
