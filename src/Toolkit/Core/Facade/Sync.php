<?php declare(strict_types=1);

namespace Salient\Core\Facade;

use Salient\Contract\Sync\SyncClassResolverInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\AbstractFacade;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Sync\Support\SyncErrorCollection;
use Salient\Sync\SyncError;
use Salient\Sync\SyncErrorBuilder;
use Salient\Sync\SyncStore;

/**
 * A facade for SyncStore
 *
 * @method static SyncStore checkHeartbeats(int $ttl = 300, bool $failEarly = true, SyncProviderInterface ...$providers) Throw an exception if a provider has an unreachable backend (see {@see SyncStore::checkHeartbeats()})
 * @method static SyncStore close(int $exitStatus = 0) Terminate the current run and close the database
 * @method static SyncStore deferredEntity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, DeferredEntity<SyncEntityInterface> $deferred) Register a deferred sync entity (see {@see SyncStore::deferredEntity()})
 * @method static SyncStore deferredRelationship(int $providerId, class-string<SyncEntityInterface> $entityType, class-string<SyncEntityInterface> $forEntityType, string $forEntityProperty, int|string $forEntityId, DeferredRelationship<SyncEntityInterface> $deferred) Register a deferred relationship
 * @method static SyncStore disableErrorReporting() Disable sync error reporting
 * @method static SyncStore enableErrorReporting() Report sync errors to the console as they occur (disabled by default)
 * @method static SyncStore entity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, SyncEntityInterface $entity) Register a sync entity (see {@see SyncStore::entity()})
 * @method static SyncStore entityType(class-string<SyncEntityInterface> $entity) Register a sync entity type and set its ID (unless already registered) (see {@see SyncStore::entityType()})
 * @method static SyncStore error(SyncError|SyncErrorBuilder $error, bool $deduplicate = false) Report an error that occurred during a sync operation
 * @method static int getDeferralCheckpoint() Get a checkpoint to delineate between deferred entities and relationships already in their respective queues, and any subsequent deferrals (see {@see SyncStore::getDeferralCheckpoint()})
 * @method static SyncEntityInterface|null getEntity(int $providerId, class-string<SyncEntityInterface> $entityType, int|string $entityId, bool|null $offline = null) Get a previously registered and/or stored sync entity (see {@see SyncStore::getEntity()})
 * @method static string|null getEntityTypeNamespace(class-string<SyncEntityInterface> $entity) Get the namespace of a sync entity type (see {@see SyncStore::getEntityTypeNamespace()})
 * @method static string|null getEntityTypeUri(class-string<SyncEntityInterface> $entity, bool $compact = true) Get the canonical URI of a sync entity type (see {@see SyncStore::getEntityTypeUri()})
 * @method static SyncErrorCollection getErrors() Get sync errors recorded so far
 * @method static string getFilename() Get the filename of the database
 * @method static class-string<SyncClassResolverInterface>|null getNamespaceResolver(class-string<SyncEntityInterface|SyncProviderInterface> $class) Get the class resolver for an entity or provider's namespace
 * @method static SyncProviderInterface|null getProvider(string $hash) Get a registered sync provider
 * @method static string getProviderHash(SyncProviderInterface $provider) Get the stable identifier of a sync provider
 * @method static int getProviderId(SyncProviderInterface $provider) Get the provider ID of a registered sync provider, starting a run if necessary
 * @method static int getRunId() Get the run ID of the current run
 * @method static string getRunUuid(bool $binary = false) Get the UUID of the current run (see {@see SyncStore::getRunUuid()})
 * @method static bool hasRunId() Check if a run has started
 * @method static bool isOpen() Check if the database is open
 * @method static SyncStore namespace(string $prefix, string $uri, string $namespace, class-string<SyncClassResolverInterface>|null $resolver = null) Register a sync entity namespace (see {@see SyncStore::namespace()})
 * @method static SyncStore provider(SyncProviderInterface $provider) Register a sync provider and set its provider ID (see {@see SyncStore::provider()})
 * @method static SyncStore reportErrors(string $successText = 'No sync errors recorded') Report sync errors recorded so far to the console (see {@see SyncStore::reportErrors()})
 * @method static SyncEntityInterface[]|null resolveDeferred(?int $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, bool $return = false) Resolve deferred sync entities and relationships recursively until no deferrals remain
 * @method static SyncEntityInterface[] resolveDeferredEntities(?int $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, ?int $providerId = null) Resolve deferred sync entities from their respective providers and/or the local entity store
 * @method static array<SyncEntityInterface[]> resolveDeferredRelationships(?int $fromCheckpoint = null, class-string<SyncEntityInterface>|null $entityType = null, class-string<SyncEntityInterface>|null $forEntityType = null, ?int $providerId = null) Resolve deferred relationships from their respective providers and/or the local entity store
 *
 * @extends AbstractFacade<SyncStore>
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
        return SyncStore::class;
    }
}
