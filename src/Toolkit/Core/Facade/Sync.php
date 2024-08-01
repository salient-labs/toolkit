<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Contract\Sync\DeferredEntityInterface;
use Salient\Contract\Sync\DeferredRelationshipInterface;
use Salient\Contract\Sync\SyncClassResolverInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncErrorCollectionInterface;
use Salient\Contract\Sync\SyncErrorInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\AbstractFacade;
use Salient\Sync\SyncStore;

/**
 * A facade for the global sync entity store
 *
 * @method static SyncStoreInterface checkProviderHeartbeats(int $ttl = 300, bool $failEarly = true, SyncProviderInterface ...$providers) Throw an exception if a sync provider's backend is unreachable (see {@see SyncStoreInterface::checkProviderHeartbeats()})
 * @method static SyncStoreInterface deferEntity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, DeferredEntityInterface<SyncEntityInterface> $entity) Register a deferred entity with the entity store (see {@see SyncStoreInterface::deferEntity()})
 * @method static SyncStoreInterface deferRelationship(int $providerId, class-string<SyncEntityInterface> $entityType, class-string<SyncEntityInterface> $forEntityType, string $forEntityProperty, int|string $forEntityId, DeferredRelationshipInterface<SyncEntityInterface> $relationship) Register a deferred relationship with the entity store
 * @method static string getBinaryRunUuid() Get the UUID of the current run in raw binary form
 * @method static SyncClassResolverInterface|null getClassResolver(class-string<SyncEntityInterface|SyncProviderInterface> $class) Get a class resolver for a sync entity or provider interface, or null if it is not in a namespace with a registered resolver
 * @method static int getDeferralCheckpoint() Get a checkpoint to delineate between deferred entities and relationships that have already been registered, and any subsequent deferrals (see {@see SyncStoreInterface::getDeferralCheckpoint()})
 * @method static SyncEntityInterface|null getEntity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, bool|null $offline = null) Get a sync entity from the entity store if it is available (see {@see SyncStoreInterface::getEntity()})
 * @method static int getEntityId(class-string<SyncEntityInterface> $entity) Get the entity ID of a registered sync entity type
 * @method static string|null getEntityPrefix(class-string<SyncEntityInterface> $entity) Get the prefix of a sync entity's namespace, or null if it is not in a registered namespace
 * @method static string|null getEntityUri(class-string<SyncEntityInterface> $entity, bool $compact = true) Get the canonical URI of a sync entity, or null if it is not in a registered namespace
 * @method static SyncErrorCollectionInterface getErrors() Get sync operation errors recorded so far
 * @method static SyncProviderInterface|null getProvider(string $signature) Get a sync provider if it is registered with the entity store
 * @method static int getProviderId(SyncProviderInterface $provider) Get the provider ID of a registered sync provider
 * @method static string getProviderSignature(SyncProviderInterface $provider) Get a stable value that uniquely identifies a sync provider with the entity store
 * @method static int getRunId() Get the run ID of the current run
 * @method static string getRunUuid() Get the UUID of the current run in hexadecimal form
 * @method static SyncStoreInterface recordError(SyncErrorInterface $error, bool $deduplicate = false) Register a non-fatal sync operation error with the entity store
 * @method static SyncStoreInterface registerEntity(class-string<SyncEntityInterface> $entity) Register a sync entity type with the entity store if it is not already registered (see {@see SyncStoreInterface::registerEntity()})
 * @method static SyncStoreInterface registerNamespace(string $prefix, string $uri, string $namespace, SyncClassResolverInterface|null $resolver = null) Register a namespace for sync entities and their provider interfaces (see {@see SyncStoreInterface::registerNamespace()})
 * @method static SyncStoreInterface registerProvider(SyncProviderInterface $provider) Register a sync provider with the entity store
 * @method static SyncEntityInterface[] resolveDeferrals(int|null $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, int|null $providerId = null) Resolve deferred entities and relationships recursively until no deferrals remain
 * @method static SyncEntityInterface[] resolveDeferredEntities(int|null $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, int|null $providerId = null) Resolve deferred entities
 * @method static SyncEntityInterface[][] resolveDeferredRelationships(int|null $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, class-string<SyncEntityInterface>|null $forEntityType = null, string|null $forEntityProperty = null, int|null $providerId = null) Resolve deferred relationships
 * @method static bool runHasStarted() Check if a run of sync operations has started
 * @method static SyncStoreInterface setEntity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, SyncEntityInterface $entity) Apply a sync entity retrieved from a provider to the entity store, resolving any matching deferred entities
 *
 * @extends AbstractFacade<SyncStoreInterface>
 *
 * @generated
 */
final class Sync extends AbstractFacade
{
    /**
     * @internal
     */
    protected static function getService()
    {
        return [
            SyncStoreInterface::class => SyncStore::class,
        ];
    }
}
