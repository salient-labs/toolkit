<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Sync\Contract\ISyncClassResolver;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\DeferredSyncEntity;
use Lkrms\Sync\Support\SyncError;
use Lkrms\Sync\Support\SyncErrorBuilder;
use Lkrms\Sync\Support\SyncErrorCollection;
use Lkrms\Sync\Support\SyncStore;

/**
 * A facade for \Lkrms\Sync\Support\SyncStore
 *
 * @method static SyncStore load(string $filename = ':memory:', string $command = '', string[] $arguments = []) Load and return an instance of the underlying SyncStore class
 * @method static SyncStore getInstance() Get the underlying SyncStore instance
 * @method static bool isLoaded() True if an underlying SyncStore instance has been loaded
 * @method static void unload() Clear the underlying SyncStore instance
 * @method static SyncStore checkHeartbeats(int $ttl = 300, bool $failEarly = true, ISyncProvider ...$providers) Throw an exception if a provider has an unreachable backend (see {@see SyncStore::checkHeartbeats()})
 * @method static SyncStore close(int $exitStatus = 0) Terminate the current run and close the database
 * @method static SyncStore deferredEntity(int $providerId, class-string<ISyncEntity> $entityType, int|string $entityId, DeferredSyncEntity $deferred) Register a deferred sync entity (see {@see SyncStore::deferredEntity()})
 * @method static SyncStore entity(int $providerId, class-string<ISyncEntity> $entityType, int|string $entityId, ISyncEntity $entity) Register a sync entity (see {@see SyncStore::entity()})
 * @method static SyncStore entityType(class-string<ISyncEntity> $entity) Register a sync entity type and set its ID (unless already registered) (see {@see SyncStore::entityType()})
 * @method static SyncStore error(SyncError|SyncErrorBuilder $error, bool $deduplicate = false, bool $toConsole = false) Report an error that occurred during a sync operation
 * @method static int getDeferredEntityCheckpoint() Get a checkpoint to delineate between entities already in the deferred entity queue and any subsequently deferred sync entities (see {@see SyncStore::getDeferredEntityCheckpoint()})
 * @method static ISyncEntity|null getEntity(int $providerId, class-string<ISyncEntity> $entityType, int|string $entityId, bool|null $offline = null) Get a previously registered and/or stored sync entity (see {@see SyncStore::getEntity()})
 * @method static string|null getEntityTypeNamespace(class-string<ISyncEntity> $entity) Get the namespace of a sync entity type (see {@see SyncStore::getEntityTypeNamespace()})
 * @method static string|null getEntityTypeUri(class-string<ISyncEntity> $entity, bool $compact = true) Get the canonical URI of a sync entity type (see {@see SyncStore::getEntityTypeUri()})
 * @method static SyncErrorCollection getErrors() Get sync operation errors recorded so far
 * @method static string|null getFilename() Get the filename of the database
 * @method static class-string<ISyncClassResolver>|null getNamespaceResolver(class-string<ISyncEntity|ISyncProvider> $class) Get the class resolver for an entity or provider's namespace
 * @method static ISyncProvider|null getProvider(string $hash) Get a registered sync provider
 * @method static string getProviderHash(ISyncProvider $provider) Get the stable identifier of a sync provider
 * @method static int getProviderId(ISyncProvider $provider) Get the provider ID of a registered sync provider, starting a run if necessary
 * @method static int getRunId() Get the run ID of the current run
 * @method static string getRunUuid(bool $binary = false) Get the UUID of the current run (see {@see SyncStore::getRunUuid()})
 * @method static bool isOpen() Check if a database is open
 * @method static SyncStore namespace(string $prefix, string $uri, string $namespace, class-string<ISyncClassResolver>|null $resolver = null) Register a sync entity namespace (see {@see SyncStore::namespace()})
 * @method static SyncStore provider(ISyncProvider $provider) Register a sync provider and set its provider ID (see {@see SyncStore::provider()})
 * @method static SyncStore reportErrors(string $successText = 'No sync errors recorded') Report sync operation errors to the console (see {@see SyncStore::reportErrors()})
 * @method static ISyncEntity[] resolveDeferredEntities(?int $fromCheckpoint = null, ?int $providerId = null, class-string<ISyncEntity> $entityType = null, bool|null $offline = null) Resolve deferred sync entities from their respective providers and/or the local entity store (see {@see SyncStore::resolveDeferredEntities()})
 *
 * @uses SyncStore
 *
 * @extends Facade<SyncStore>
 */
final class Sync extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return SyncStore::class;
    }
}
