<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Contract\Sync\DeferredEntityInterface;
use Salient\Contract\Sync\DeferredRelationshipInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncErrorCollectionInterface;
use Salient\Contract\Sync\SyncErrorInterface;
use Salient\Contract\Sync\SyncNamespaceHelperInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Sync\SyncStore;

/**
 * A facade for the global sync entity store
 *
 * @method static SyncStoreInterface checkProviderHeartbeats(int $ttl = 300, bool $failEarly = true, SyncProviderInterface ...$providers) Throw an exception if a sync provider's backend is unreachable (see {@see SyncStoreInterface::checkProviderHeartbeats()})
 * @method static SyncStoreInterface deferEntity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, DeferredEntityInterface<SyncEntityInterface> $entity) Register a deferred entity with the entity store (see {@see SyncStoreInterface::deferEntity()})
 * @method static SyncStoreInterface deferRelationship(int $providerId, class-string<SyncEntityInterface> $entityType, class-string<SyncEntityInterface> $forEntityType, string $forEntityProperty, int|string $forEntityId, DeferredRelationshipInterface<SyncEntityInterface> $relationship) Register a deferred relationship with the entity store
 * @method static string getBinaryRunUuid() Get the UUID of the current run in raw binary form
 * @method static int getDeferralCheckpoint() Get a checkpoint to delineate between deferred entities and relationships that have already been registered, and any subsequent deferrals (see {@see SyncStoreInterface::getDeferralCheckpoint()})
 * @method static SyncEntityInterface|null getEntity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, bool|null $offline = null) Get a sync entity from the entity store if it is available (see {@see SyncStoreInterface::getEntity()})
 * @method static class-string<SyncEntityInterface> getEntityType(int $entityTypeId) Get a sync entity type registered with the entity store
 * @method static int getEntityTypeId(class-string<SyncEntityInterface> $entityType) Get the entity type ID of a registered sync entity type
 * @method static string getEntityTypeUri(class-string<SyncEntityInterface> $entityType, bool $compact = true) Get the canonical URI of a sync entity type
 * @method static SyncErrorCollectionInterface getErrors() Get sync operation errors recorded so far
 * @method static SyncNamespaceHelperInterface|null getNamespaceHelper(class-string<SyncEntityInterface|SyncProviderInterface> $class) Get a namespace helper for a sync entity type or provider interface, or null if it is not in a namespace with a registered helper
 * @method static string|null getNamespacePrefix(class-string<SyncEntityInterface|SyncProviderInterface> $class) Get a namespace prefix for a sync entity type or provider interface, or null if it is not in a registered namespace
 * @method static SyncProviderInterface getProvider(string|int $provider) Get a sync provider registered with the entity store (see {@see SyncStoreInterface::getProvider()})
 * @method static int getProviderId(SyncProviderInterface|string $provider) Get the provider ID by which a registered sync provider is uniquely identified in the entity store's backing database (see {@see SyncStoreInterface::getProviderId()})
 * @method static string getProviderSignature(SyncProviderInterface $provider) Get a stable value that uniquely identifies a sync provider with the entity store and is independent of its backing database
 * @method static int getRunId() Get the run ID of the current run
 * @method static string getRunUuid() Get the UUID of the current run in hexadecimal form
 * @method static bool hasEntityType(class-string<SyncEntityInterface> $entityType) Check if a sync entity type is registered with the entity store
 * @method static bool hasProvider(SyncProviderInterface|string $provider) Check if a sync provider is registered with the entity store (see {@see SyncStoreInterface::hasProvider()})
 * @method static SyncStoreInterface recordError(SyncErrorInterface $error, bool $deduplicate = false) Register a non-fatal sync operation error with the entity store
 * @method static SyncStoreInterface registerEntityType(class-string<SyncEntityInterface> $entityType) Register a sync entity type with the entity store if it is not already registered (see {@see SyncStoreInterface::registerEntityType()})
 * @method static SyncStoreInterface registerNamespace(string $prefix, string $uri, string $namespace, SyncNamespaceHelperInterface|null $helper = null) Register a namespace for sync entities and their provider interfaces (see {@see SyncStoreInterface::registerNamespace()})
 * @method static SyncStoreInterface registerProvider(SyncProviderInterface $provider) Register a sync provider with the entity store
 * @method static SyncEntityInterface[] resolveDeferrals(int|null $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, int|null $providerId = null) Resolve deferred entities and relationships recursively until no deferrals remain
 * @method static SyncEntityInterface[] resolveDeferredEntities(int|null $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, int|null $providerId = null) Resolve deferred entities
 * @method static SyncEntityInterface[][] resolveDeferredRelationships(int|null $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, class-string<SyncEntityInterface>|null $forEntityType = null, string|null $forEntityProperty = null, int|null $providerId = null) Resolve deferred relationships
 * @method static bool runHasStarted() Check if a run of sync operations has started
 * @method static SyncStoreInterface setEntity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, SyncEntityInterface $entity) Apply a sync entity retrieved from a provider to the entity store, resolving any matching deferred entities
 *
 * @extends Facade<SyncStoreInterface>
 *
 * @generated
 */
final class Sync extends Facade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return [
            SyncStoreInterface::class,
            SyncStore::class,
        ];
    }
}
