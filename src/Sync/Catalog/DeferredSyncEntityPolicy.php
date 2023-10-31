<?php declare(strict_types=1);

namespace Lkrms\Sync\Catalog;

use Lkrms\Concept\Enumeration;
use Lkrms\Sync\Support\DeferredRelationship;
use Lkrms\Sync\Support\DeferredSyncEntity;
use Lkrms\Sync\Support\SyncStore;

/**
 * Policies for deferral of sync entities and relationships
 *
 * @extends Enumeration<int>
 */
final class DeferredSyncEntityPolicy extends Enumeration
{
    /**
     * Do not resolve deferred entities or relationships
     *
     * If {@see SyncStore::resolveDeferredEntities()} and
     * {@see SyncStore::resolveDeferredRelationships()} are not called manually,
     * unresolved {@see DeferredSyncEntity} and {@see DeferredRelationship}
     * instances may appear in object graphs returned by sync operations.
     */
    public const DO_NOT_RESOLVE = 0;

    /**
     * Resolve deferred entities and relationships immediately
     *
     * This is the least efficient policy because it produces the most round
     * trips, but it does guarantee the return of fully resolved object graphs.
     */
    public const RESOLVE_EARLY = 1;

    /**
     * Resolve deferred entities and relationships after reaching the end of
     * each stream of entity data
     *
     * This policy minimises the number of round trips to the backend, but
     * unresolved {@see DeferredSyncEntity} and {@see DeferredRelationship}
     * instances may appear in object graphs until they have been fully
     * traversed.
     */
    public const RESOLVE_LATE = 2;
}
