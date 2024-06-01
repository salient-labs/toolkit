<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Sync\Support\SyncErrorCollection;
use Salient\Sync\SyncError;
use InvalidArgumentException;
use LogicException;

interface SyncStoreInterface
{
    /**
     * Register a namespace for sync entities and their provider interfaces
     *
     * A prefix can only be associated with one namespace per entity store and
     * cannot be changed without resetting its backing database.
     *
     * If `$prefix` was registered on a previous run, its URI and PHP namespace
     * are updated if they differ. This is by design and is intended to
     * facilitate refactoring.
     *
     * If `$resolver` is `null`, the entity store assumes sync entities in
     * `$namespace` are serviced by provider interfaces called
     * `<entity-namespace>\Provider\<entity>Provider`, e.g. `Acme\Sync\User`
     * entities would be serviced by `Acme\Sync\Provider\UserProvider`. Provide
     * a {@see SyncClassResolverInterface} to modify this behaviour.
     *
     * @param string $prefix A short alternative to `$uri`. Case-insensitive.
     * Must be unique to the entity store. Must be a scheme name compliant with
     * Section 3.1 of \[RFC3986], i.e. a match for the regular expression
     * `^[a-zA-Z][a-zA-Z0-9+.-]*$`.
     * @param string $uri A globally unique namespace URI.
     * @param string $namespace The PHP namespace that contains sync entity
     * classes and their respective provider interfaces.
     * @return $this
     * @throws LogicException if the prefix is already registered.
     * @throws InvalidArgumentException if the prefix is invalid.
     */
    public function registerNamespace(
        string $prefix,
        string $uri,
        string $namespace,
        ?SyncClassResolverInterface $resolver = null
    );

    /**
     * Get a class resolver for a sync entity or provider interface, or null if
     * it is not in a namespace with a registered resolver
     *
     * @param class-string<SyncEntityInterface|SyncProviderInterface> $class
     */
    public function getClassResolver(string $class): ?SyncClassResolverInterface;

    /**
     * Get a stable value that uniquely identifies a sync provider with the
     * entity store
     */
    public function getProviderSignature(SyncProviderInterface $provider): string;

    /**
     * Register a sync provider with the entity store
     *
     * @return $this
     * @throws LogicException if the provider is already registered.
     */
    public function registerProvider(SyncProviderInterface $provider);

    /**
     * Get the provider ID of a registered sync provider
     *
     * @throws LogicException if the provider is not registered.
     */
    public function getProviderId(SyncProviderInterface $provider): int;

    /**
     * Get a sync provider if it is registered with the entity store
     */
    public function getProvider(string $signature): ?SyncProviderInterface;

    /**
     * Throw an exception if a sync provider's backend is unreachable
     *
     * If no providers are given, every provider registered with the entity
     * store is checked.
     *
     * The same provider may be given multiple times, but it will only be
     * checked once.
     *
     * @return $this
     */
    public function checkProviderHeartbeats(
        int $ttl = 300,
        bool $failEarly = true,
        SyncProviderInterface ...$providers
    );

    /**
     * Register a sync entity type with the entity store if it is not already
     * registered
     *
     * `$entity` is case-sensitive and must exactly match the declared name of
     * the sync entity class.
     *
     * @param class-string<SyncEntityInterface> $entity
     * @return $this
     */
    public function registerEntity(string $entity);

    /**
     * Get the entity ID of a registered sync entity type
     *
     * @param class-string<SyncEntityInterface> $entity
     * @throws LogicException if the entity type is not registered.
     */
    public function getEntityId(string $entity): int;

    /**
     * Get the canonical URI of a sync entity, or null if it is not in a
     * registered namespace
     *
     * @param class-string<SyncEntityInterface> $entity
     */
    public function getEntityUri(string $entity, bool $compact = true): ?string;

    /**
     * Get the prefix of a sync entity's namespace, or null if it is not in a
     * registered namespace
     *
     * @param class-string<SyncEntityInterface> $entity
     */
    public function getEntityPrefix(string $entity): ?string;

    /**
     * Apply a sync entity retrieved from a provider to the entity store,
     * resolving any matching deferred entities
     *
     * @param class-string<SyncEntityInterface> $entityType
     * @param int|string $entityId
     * @return $this
     * @throws LogicException if the entity has already been applied to the
     * entity store or retrieved via {@see SyncStoreInterface::getEntity()} in
     * the current run.
     */
    public function setEntity(
        int $providerId,
        string $entityType,
        $entityId,
        SyncEntityInterface $entity
    );

    /**
     * Get a sync entity from the entity store if it is available
     *
     * @param class-string<SyncEntityInterface> $entityType
     * @param int|string $entityId
     * @param bool|null $offline - `null` (default) or `true`: allow the entity
     * to be retrieved from the local entity store
     * - `false`: do not retrieve the entity from the local entity store; return
     *   `null` if it has not been applied to the store in the current run
     */
    public function getEntity(
        int $providerId,
        string $entityType,
        $entityId,
        ?bool $offline = null
    ): ?SyncEntityInterface;

    /**
     * Register a deferred entity with the entity store
     *
     * If a matching entity has already been applied to the entity store, the
     * deferred entity is resolved immediately, otherwise it is queued for
     * retrieval from the provider.
     *
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entityType
     * @param int|string $entityId
     * @param DeferredEntity<TEntity> $entity
     * @return $this
     */
    public function deferEntity(
        int $providerId,
        string $entityType,
        $entityId,
        DeferredEntity $entity
    );

    /**
     * Register a deferred relationship with the entity store
     *
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entityType
     * @param class-string<SyncEntityInterface> $forEntityType
     * @param int|string $forEntityId
     * @param DeferredRelationship<TEntity> $relationship
     * @return $this
     */
    public function deferRelationship(
        int $providerId,
        string $entityType,
        string $forEntityType,
        string $forEntityProperty,
        $forEntityId,
        DeferredRelationship $relationship
    );

    /**
     * Get a checkpoint to delineate between deferred entities and relationships
     * that have already been registered, and any subsequent deferrals
     *
     * The value returned by this method can be used to limit the scope of
     * {@see SyncStoreInterface::resolveDeferrals()},
     * {@see SyncStoreInterface::resolveDeferredEntities()} and
     * {@see SyncStoreInterface::resolveDeferredRelationships()}, e.g. to
     * entities and relationships deferred during a particular operation.
     */
    public function getDeferralCheckpoint(): int;

    /**
     * Resolve deferred entities and relationships recursively until no
     * deferrals remain
     *
     * @param class-string<SyncEntityInterface>|null $entityType
     * @return SyncEntityInterface[]
     */
    public function resolveDeferrals(
        ?int $fromCheckpoint = null,
        ?string $entityType = null,
        ?int $providerId = null
    ): array;

    /**
     * Resolve deferred entities
     *
     * @param class-string<SyncEntityInterface>|null $entityType
     * @return SyncEntityInterface[]
     */
    public function resolveDeferredEntities(
        ?int $fromCheckpoint = null,
        ?string $entityType = null,
        ?int $providerId = null
    ): array;

    /**
     * Resolve deferred relationships
     *
     * @param class-string<SyncEntityInterface>|null $entityType
     * @param class-string<SyncEntityInterface>|null $forEntityType
     * @return SyncEntityInterface[][]
     */
    public function resolveDeferredRelationships(
        ?int $fromCheckpoint = null,
        ?string $entityType = null,
        ?string $forEntityType = null,
        ?string $forEntityProperty = null,
        ?int $providerId = null
    ): array;

    /**
     * Check if a run of sync operations has started
     */
    public function runHasStarted(): bool;

    /**
     * Get the run ID of the current run
     *
     * @throws LogicException if a run of sync operations has not started.
     */
    public function getRunId(): int;

    /**
     * Get the UUID of the current run in hexadecimal form
     *
     * @throws LogicException if a run of sync operations has not started.
     */
    public function getRunUuid(): string;

    /**
     * Get the UUID of the current run in raw binary form
     *
     * @throws LogicException if a run of sync operations has not started.
     */
    public function getBinaryRunUuid(): string;

    /**
     * Register a non-fatal sync operation error with the entity store
     *
     * @return $this
     */
    public function recordError(SyncError $error, bool $deduplicate = false);

    /**
     * Get sync operation errors recorded so far
     */
    public function getErrors(): SyncErrorCollection;

    /**
     * Write a summary of sync operation errors recorded so far to the console
     *
     * Output is written to the console with level:
     * - NOTICE if no errors have been recorded
     * - ERROR if one or more recorded errors have level ERROR or higher
     * - WARNING if all recorded errors have level WARNING or lower
     *
     * @return $this
     */
    public function reportErrors(string $successText = 'No sync errors recorded');
}
