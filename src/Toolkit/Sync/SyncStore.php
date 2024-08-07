<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Sync\DeferralPolicy;
use Salient\Contract\Sync\DeferredEntityInterface;
use Salient\Contract\Sync\DeferredRelationshipInterface;
use Salient\Contract\Sync\HydrationPolicy;
use Salient\Contract\Sync\SyncClassResolverInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncErrorCollectionInterface;
use Salient\Contract\Sync\SyncErrorInterface;
use Salient\Contract\Sync\SyncErrorType;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Exception\MethodNotImplementedException;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Err;
use Salient\Core\Facade\Event;
use Salient\Core\AbstractStore;
use Salient\Sync\Event\SyncStoreLoadedEvent;
use Salient\Sync\Exception\SyncProviderBackendUnreachableException;
use Salient\Sync\Exception\SyncProviderHeartbeatCheckFailedException;
use Salient\Sync\Exception\SyncStoreException;
use Salient\Sync\Support\SyncErrorCollection;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Json;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;

/**
 * Tracks the state of entities synced to and from third-party backends in a
 * local SQLite database
 *
 * Creating a {@see SyncStore} instance starts a sync operation run that must be
 * terminated by calling {@see SyncStore::close()}, otherwise a failed run is
 * recorded.
 */
final class SyncStore extends AbstractStore implements SyncStoreInterface
{
    private ?int $RunId = null;
    private ?string $RunUuid = null;

    /**
     * Provider ID => provider
     *
     * @var array<int,SyncProviderInterface>
     */
    private $Providers = [];

    /**
     * Provider hash => provider ID
     *
     * @var array<string,int>
     */
    private $ProviderMap = [];

    /**
     * Entity type ID => entity class
     *
     * @var array<int,string>
     * @phpstan-ignore-next-line
     */
    private $EntityTypes = [];

    /**
     * Entity class => entity type ID
     *
     * @var array<string,int>
     */
    private $EntityTypeMap = [];

    /**
     * Prefix => lowercase PHP namespace
     *
     * @var array<string,string>|null
     */
    private $NamespacesByPrefix;

    /**
     * Prefix => namespace base URI
     *
     * @var array<string,string>|null
     */
    private $NamespaceUrisByPrefix;

    /**
     * Prefix => resolver
     *
     * @var array<string,SyncClassResolverInterface>|null
     */
    private $NamespaceResolversByPrefix;

    /**
     * Provider ID => entity type ID => entity ID => entity
     *
     * @var array<int,array<int,array<int|string,SyncEntityInterface>>>
     */
    private $Entities;

    /**
     * SPL object ID => checkpoint
     *
     * @var array<int,int>
     */
    private $EntityCheckpoints;

    /**
     * Provider ID => entity type ID => entity ID => [ deferred entity ]
     *
     * @var array<int,array<int,array<int|string,array<DeferredEntityInterface<SyncEntityInterface>>>>>
     */
    private $DeferredEntities = [];

    /**
     * Provider ID => entity type ID => requesting entity type ID => requesting
     * entity property => requesting entity ID => [ deferred relationship ]
     *
     * @var array<int,array<int,array<int,array<string,array<int|string,DeferredRelationshipInterface<SyncEntityInterface>[]>>>>>
     */
    private $DeferredRelationships = [];

    /** @var SyncErrorCollection */
    private $Errors;

    /**
     * Prefix => true
     *
     * @var array<string,true>
     */
    private $RegisteredNamespaces = [];

    /** @var int */
    private $DeferralCheckpoint = 0;
    /** @var string|null */
    private $Command;
    /** @var string[]|null */
    private $Arguments;

    /**
     * Deferred provider registrations
     *
     * @var SyncProviderInterface[]
     */
    private $DeferredProviders = [];

    /**
     * Deferred entity type registrations
     *
     * @var class-string<SyncEntityInterface>[]
     */
    private $DeferredEntityTypes = [];

    /**
     * Deferred namespace registrations
     *
     * Prefix => [ namespace base URI, PHP namespace, class resolver ]
     *
     * @var array<string,array{string,string,SyncClassResolverInterface|null}>
     */
    private $DeferredNamespaces = [];

    /**
     * Creates a new SyncStore object
     *
     * @param string $command The canonical name of the command performing sync
     * operations (e.g. a qualified class and/or method name).
     * @param string[] $arguments Arguments passed to the command.
     */
    public function __construct(
        string $filename = ':memory:',
        string $command = '',
        array $arguments = []
    ) {
        $this->assertCanUpsert();

        $this->Errors = new SyncErrorCollection();
        $this->Command = $command;
        $this->Arguments = $arguments;

        $this->openDb(
            $filename,
            <<<SQL
CREATE TABLE IF NOT EXISTS
  _sync_run (
    run_id INTEGER NOT NULL PRIMARY KEY,
    run_uuid BLOB NOT NULL UNIQUE,
    run_command TEXT NOT NULL,
    run_arguments_json TEXT NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME,
    exit_status INTEGER,
    error_count INTEGER,
    warning_count INTEGER,
    errors_json TEXT
  );

CREATE TABLE IF NOT EXISTS
  _sync_provider (
    provider_id INTEGER NOT NULL PRIMARY KEY,
    provider_hash BLOB NOT NULL UNIQUE,
    provider_class TEXT NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
  );

CREATE TABLE IF NOT EXISTS
  _sync_entity_type (
    entity_type_id INTEGER NOT NULL PRIMARY KEY,
    entity_type_class TEXT NOT NULL UNIQUE,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
  );

CREATE TABLE IF NOT EXISTS
  _sync_entity_type_state (
    provider_id INTEGER NOT NULL,
    entity_type_id INTEGER NOT NULL,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_sync DATETIME,
    PRIMARY KEY (provider_id, entity_type_id),
    FOREIGN KEY (provider_id) REFERENCES _sync_provider,
    FOREIGN KEY (entity_type_id) REFERENCES _sync_entity_type
  );

CREATE TABLE IF NOT EXISTS
  _sync_entity (
    provider_id INTEGER NOT NULL,
    entity_type_id INTEGER NOT NULL,
    entity_id TEXT NOT NULL,
    canonical_id TEXT,
    is_dirty INTEGER NOT NULL DEFAULT 0,
    is_deleted INTEGER NOT NULL DEFAULT 0,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_sync DATETIME,
    entity_json TEXT NOT NULL,
    PRIMARY KEY (provider_id, entity_type_id, entity_id),
    FOREIGN KEY (provider_id) REFERENCES _sync_provider,
    FOREIGN KEY (entity_type_id) REFERENCES _sync_entity_type
  ) WITHOUT ROWID;

CREATE TABLE IF NOT EXISTS
  _sync_entity_namespace (
    entity_namespace_id INTEGER NOT NULL PRIMARY KEY,
    entity_namespace_prefix TEXT NOT NULL UNIQUE,
    base_uri TEXT NOT NULL,
    php_namespace TEXT NOT NULL,
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME DEFAULT CURRENT_TIMESTAMP
  );

SQL
        );

        Event::dispatch(new SyncStoreLoadedEvent($this));
    }

    /**
     * Terminate the current run and close the database
     */
    public function close(int $exitStatus = 0): void
    {
        // Don't start a run now
        if (!$this->isOpen() || $this->RunId === null) {
            $this->closeDb();
            return;
        }

        $sql = <<<SQL
UPDATE
  _sync_run
SET
  finished_at = CURRENT_TIMESTAMP,
  exit_status = :exit_status,
  error_count = :error_count,
  warning_count = :warning_count,
  errors_json = :errors_json
WHERE
  run_uuid = :run_uuid;
SQL;

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':exit_status', $exitStatus, \SQLITE3_INTEGER);
        $stmt->bindValue(':run_uuid', $this->RunUuid, \SQLITE3_BLOB);
        $stmt->bindValue(':error_count', $this->Errors->getErrorCount(), \SQLITE3_INTEGER);
        $stmt->bindValue(':warning_count', $this->Errors->getWarningCount(), \SQLITE3_INTEGER);
        $stmt->bindValue(':errors_json', Json::stringify($this->Errors), \SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $this->closeDb();
    }

    /**
     * @phpstan-assert-if-true !null $this->RunId
     * @phpstan-assert-if-true !null $this->RunUuid
     * @phpstan-assert-if-true !null $this->NamespacesByPrefix
     * @phpstan-assert-if-true !null $this->NamespaceUrisByPrefix
     */
    public function runHasStarted(): bool
    {
        return $this->RunId !== null;
    }

    /**
     * @inheritDoc
     */
    public function getRunId(): int
    {
        $this->assertRunHasStarted();

        return $this->RunId;
    }

    /**
     * @inheritDoc
     */
    public function getRunUuid(): string
    {
        $this->assertRunHasStarted();

        return Get::uuid($this->RunUuid);
    }

    /**
     * @inheritDoc
     */
    public function getBinaryRunUuid(): string
    {
        $this->assertRunHasStarted();

        return $this->RunUuid;
    }

    /**
     * @phpstan-assert !null $this->RunId
     * @phpstan-assert !null $this->RunUuid
     */
    private function assertRunHasStarted(): void
    {
        if ($this->RunId === null) {
            throw new LogicException('Run has not started');
        }
    }

    /**
     * @inheritDoc
     */
    public function registerProvider(SyncProviderInterface $provider)
    {
        // Don't start a run just to register a provider
        if (!$this->runHasStarted()) {
            $this->DeferredProviders[] = $provider;
            return $this;
        }

        $class = get_class($provider);
        $hash = $this->getProviderSignature($provider);

        if (isset($this->ProviderMap[$hash])) {
            throw new LogicException(sprintf(
                'Provider already registered: %s',
                $class,
            ));
        }

        // Update `last_seen` if the provider is already in the database
        $sql = <<<SQL
INSERT INTO
  _sync_provider (provider_hash, provider_class)
VALUES
  (:provider_hash, :provider_class) ON CONFLICT (provider_hash) DO
UPDATE
SET
  last_seen = CURRENT_TIMESTAMP;
SQL;
        $stmt = $this->prepare($sql);
        $stmt->bindValue(':provider_hash', $hash, \SQLITE3_BLOB);
        $stmt->bindValue(':provider_class', $class, \SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $sql = <<<SQL
SELECT
  provider_id
FROM
  _sync_provider
WHERE
  provider_hash = :provider_hash;
SQL;
        $stmt = $this->prepare($sql);
        $stmt->bindValue(':provider_hash', $hash, \SQLITE3_BLOB);
        $result = $this->execute($stmt);
        /** @var array{int}|false */
        $row = $result->fetchArray(\SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            throw new SyncStoreException('Error retrieving provider ID');
        }

        $providerId = $row[0];
        $this->Providers[$providerId] = $provider;
        $this->ProviderMap[$hash] = $providerId;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getProviderId(SyncProviderInterface $provider): int
    {
        if (!$this->runHasStarted()) {
            $this->check();
        }

        $hash = $this->getProviderSignature($provider);
        $id = $this->ProviderMap[$hash] ?? null;
        if ($id === null) {
            throw new LogicException(sprintf(
                'Provider not registered: %s',
                get_class($provider),
            ));
        }
        return $id;
    }

    /**
     * @inheritDoc
     */
    public function getProvider(string $signature): ?SyncProviderInterface
    {
        // Don't start a run just to get a provider
        if (!$this->runHasStarted()) {
            foreach ($this->DeferredProviders as $provider) {
                if ($this->getProviderSignature($provider) === $signature) {
                    return $provider;
                }
            }
            return null;
        }

        $id = $this->ProviderMap[$signature] ?? null;
        if ($id === null) {
            return null;
        }
        return $this->Providers[$id];
    }

    /**
     * @inheritDoc
     */
    public function getProviderSignature(SyncProviderInterface $provider): string
    {
        $class = get_class($provider);
        return Get::binaryHash(implode("\0", [
            $class,
            ...$provider->getBackendIdentifier(),
        ]));
    }

    /**
     * @inheritDoc
     */
    public function registerEntity(string $entity)
    {
        // Don't start a run just to register an entity type
        if (!$this->runHasStarted()) {
            $this->DeferredEntityTypes[] = $entity;
            return $this;
        }

        if (isset($this->EntityTypeMap[$entity])) {
            return $this;
        }

        $class = new ReflectionClass($entity);

        if ($entity !== $class->getName()) {
            throw new LogicException(sprintf(
                'Not an exact match for declared class (%s expected): %s',
                $class->getName(),
                $entity,
            ));
        }

        if (!$class->implementsInterface(SyncEntityInterface::class)) {
            throw new LogicException(sprintf(
                'Does not implement %s: %s',
                SyncEntityInterface::class,
                $entity,
            ));
        }

        // Update `last_seen` if the entity type is already in the database
        $sql = <<<SQL
INSERT INTO
  _sync_entity_type (entity_type_class)
VALUES
  (:entity_type_class) ON CONFLICT (entity_type_class) DO
UPDATE
SET
  last_seen = CURRENT_TIMESTAMP;
SQL;
        $stmt = $this->prepare($sql);
        $stmt->bindValue(':entity_type_class', $entity, \SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $sql = <<<SQL
SELECT
  entity_type_id
FROM
  _sync_entity_type
WHERE
  entity_type_class = :entity_type_class;
SQL;
        $stmt = $this->prepare($sql);
        $stmt->bindValue(':entity_type_class', $entity, \SQLITE3_TEXT);
        $result = $this->execute($stmt);
        /** @var array{int}|false */
        $row = $result->fetchArray(\SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            throw new SyncStoreException('Error retrieving entity type ID');
        }

        $this->EntityTypes[$row[0]] = $entity;
        $this->EntityTypeMap[$entity] = $row[0];

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(string $entity): int
    {
        if (!$this->runHasStarted()) {
            $this->check();
        }

        $id = $this->EntityTypeMap[$entity] ?? null;
        if ($id === null) {
            throw new LogicException(sprintf(
                'Entity not registered: %s',
                $entity,
            ));
        }
        return $id;
    }

    /**
     * @inheritDoc
     */
    public function registerNamespace(
        string $prefix,
        string $uri,
        string $namespace,
        ?SyncClassResolverInterface $resolver = null
    ) {
        $prefix = Str::lower($prefix);
        if (isset($this->RegisteredNamespaces[$prefix])
            || (!$this->runHasStarted()
                && isset($this->DeferredNamespaces[$prefix]))) {
            throw new LogicException(sprintf(
                'Prefix already registered: %s',
                $prefix,
            ));
        }

        // Namespaces are validated and normalised before deferral because
        // `classToNamespace()` resolves entity classes without starting a run.
        // `$DeferredNamespaces` is used to ensure it's only done once.
        if (!isset($this->DeferredNamespaces[$prefix])) {
            if (!Regex::match('/^[a-z][-a-z0-9+.]*$/iD', $prefix)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid prefix: %s',
                    $prefix,
                ));
            }
            $uri = rtrim($uri, '/') . '/';
            $namespace = trim($namespace, '\\') . '\\';
        }

        // Don't start a run just to register a namespace
        if (!$this->runHasStarted()) {
            $this->DeferredNamespaces[$prefix] = [$uri, $namespace, $resolver];
            return $this;
        }

        // Update `last_seen` if the namespace is already in the database
        $sql = <<<SQL
INSERT INTO
  _sync_entity_namespace (entity_namespace_prefix, base_uri, php_namespace)
VALUES
  (
    :entity_namespace_prefix,
    :base_uri,
    :php_namespace
  ) ON CONFLICT (entity_namespace_prefix) DO
UPDATE
SET
  base_uri = excluded.base_uri,
  php_namespace = excluded.php_namespace,
  last_seen = CURRENT_TIMESTAMP;
SQL;
        $stmt = $this->prepare($sql);
        $stmt->bindValue(':entity_namespace_prefix', $prefix, \SQLITE3_TEXT);
        $stmt->bindValue(':base_uri', $uri, \SQLITE3_TEXT);
        $stmt->bindValue(':php_namespace', $namespace, \SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $this->RegisteredNamespaces[$prefix] = true;

        if ($resolver) {
            $this->NamespaceResolversByPrefix[$prefix] = $resolver;
        }

        // Don't reload while bootstrapping
        if ($this->NamespacesByPrefix === null) {
            return $this;
        }

        return $this->reload();
    }

    /**
     * @inheritDoc
     */
    public function getEntityUri(string $entity, bool $compact = true): ?string
    {
        $prefix = $this->classToNamespace($entity, $uri, $namespace);
        if ($prefix === null) {
            return null;
        }
        $entity = str_replace('\\', '/', substr(ltrim($entity, '\\'), strlen($namespace)));

        return $compact
            ? "{$prefix}:{$entity}"
            : "{$uri}{$entity}";
    }

    /**
     * @inheritDoc
     */
    public function getEntityPrefix(string $entity): ?string
    {
        return $this->classToNamespace($entity);
    }

    /**
     * @inheritDoc
     */
    public function getClassResolver(string $class): ?SyncClassResolverInterface
    {
        if ($this->classToNamespace(
            $class,
            $uri,
            $namespace,
            $resolver
        ) === null) {
            return null;
        }

        return $resolver;
    }

    /**
     * @param class-string<SyncEntityInterface|SyncProviderInterface> $class
     * @param-out SyncClassResolverInterface|null $resolver
     */
    private function classToNamespace(
        string $class,
        ?string &$uri = null,
        ?string &$namespace = null,
        ?SyncClassResolverInterface &$resolver = null
    ): ?string {
        $class = ltrim($class, '\\');
        $lower = Str::lower($class);

        // Don't start a run just to resolve a class to a namespace
        if (!$this->runHasStarted()) {
            foreach ($this->DeferredNamespaces as $prefix => [$_uri, $_namespace, $_resolver]) {
                $_namespace = Str::lower($_namespace);
                if (strpos($lower, $_namespace) === 0) {
                    $uri = $_uri;
                    $namespace = $_namespace;
                    $resolver = $_resolver;
                    return $prefix;
                }
            }
            return null;
        }

        foreach ($this->NamespacesByPrefix as $prefix => $_namespace) {
            if (strpos($lower, $_namespace) === 0) {
                $uri = $this->NamespaceUrisByPrefix[$prefix];
                $namespace = $this->NamespacesByPrefix[$prefix];
                $resolver = $this->NamespaceResolversByPrefix[$prefix] ?? null;
                return $prefix;
            }
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function setEntity(
        int $providerId,
        string $entityType,
        $entityId,
        SyncEntityInterface $entity
    ) {
        $entityTypeId = $this->EntityTypeMap[$entityType];
        if (isset($this->Entities[$providerId][$entityTypeId][$entityId])) {
            throw new LogicException('Entity already registered');
        }
        $this->Entities[$providerId][$entityTypeId][$entityId] = $entity;
        $this->EntityCheckpoints[spl_object_id($entity)] = $this->DeferralCheckpoint++;

        // Resolve the entity's entries in the deferred entity queue (if any)
        $deferred = $this->DeferredEntities[$providerId][$entityTypeId][$entityId] ?? null;
        if ($deferred) {
            foreach ($deferred as $i => $deferredEntity) {
                $deferredEntity->replace($entity);
                unset($this->DeferredEntities[$providerId][$entityTypeId][$entityId][$i]);
            }
            unset($this->DeferredEntities[$providerId][$entityTypeId][$entityId]);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getEntity(
        int $providerId,
        string $entityType,
        $entityId,
        ?bool $offline = null
    ): ?SyncEntityInterface {
        $entityTypeId = $this->EntityTypeMap[$entityType];
        $entity = $this->Entities[$providerId][$entityTypeId][$entityId] ?? null;
        if ($entity || $offline === false) {
            return $entity;
        }
        return null;
    }

    /**
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entityType
     * @param DeferredEntityInterface<TEntity> $entity
     */
    public function deferEntity(
        int $providerId,
        string $entityType,
        $entityId,
        DeferredEntityInterface $entity
    ) {
        $entityTypeId = $this->EntityTypeMap[$entityType];
        /** @var TEntity|null */
        $_entity = $this->Entities[$providerId][$entityTypeId][$entityId] ?? null;
        if ($_entity) {
            $entity->replace($_entity);
            return $this;
        }

        // Get the deferral policy of the context within which the entity was
        // deferred
        $context = $entity->getContext();
        if ($context) {
            $last = $context->last();
            if ($last) {
                $context = $last->getContext();
            }
        }
        $policy = $context
            ? $context->getDeferralPolicy()
            : null;

        $this->DeferredEntities[$providerId][$entityTypeId][$entityId][
            $this->DeferralCheckpoint++
        ] = $entity;

        // In `RESOLVE_EARLY` mode, deferred entities are added to
        // `$this->DeferredEntities` for the benefit of `$this->setEntity()`,
        // which only calls `DeferredEntityInterface::replace()` method on
        // registered instances
        if ($policy === DeferralPolicy::RESOLVE_EARLY) {
            $entity->resolve();
            return $this;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function deferRelationship(
        int $providerId,
        string $entityType,
        string $forEntityType,
        string $forEntityProperty,
        $forEntityId,
        DeferredRelationshipInterface $relationship
    ) {
        $entityTypeId = $this->EntityTypeMap[$entityType];
        $forEntityTypeId = $this->EntityTypeMap[$forEntityType];

        $deferredList =
            &$this->DeferredRelationships[$providerId][$entityTypeId][
                $forEntityTypeId
            ][$forEntityProperty][$forEntityId];

        // @phpstan-ignore-next-line
        if (isset($deferredList)) {
            throw new LogicException('Relationship already registered');
        }

        // @phpstan-ignore-next-line
        $deferredList = [];

        // Get hydration policy from the context within which the deferral was
        // created
        $context = $relationship->getContext();
        if ($context) {
            $last = $context->last();
            if ($last) {
                $context = $last->getContext();
            }
        }
        $policy = $context
            ? $context->getHydrationPolicy($entityType)
            : 0;

        if ($policy === HydrationPolicy::LAZY) {
            return $this;
        }

        if ($policy === HydrationPolicy::EAGER) {
            $relationship->resolve();
            return $this;
        }

        $deferredList[$this->DeferralCheckpoint++] = $relationship;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function resolveDeferrals(
        ?int $fromCheckpoint = null,
        ?string $entityType = null,
        ?int $providerId = null
    ): array {
        $checkpoint = $this->DeferralCheckpoint;
        do {
            // Resolve relationships first because they typically deliver
            // multiple entities per round trip, some of which may be in the
            // deferred entity queue
            $deferred = $this->resolveDeferredRelationships($fromCheckpoint, $entityType, null, null, $providerId);
            if ($deferred) {
                foreach ($deferred as $relationship) {
                    foreach ($relationship as $entity) {
                        $objectId = spl_object_id($entity);
                        if ($this->EntityCheckpoints[$objectId] < $checkpoint) {
                            continue;
                        }
                        $resolved[$objectId] = $entity;
                    }
                }
                continue;
            }

            $deferred = $this->resolveDeferredEntities($fromCheckpoint, $entityType, $providerId);
            if (!$deferred) {
                continue;
            }
            foreach ($deferred as $entity) {
                $resolved[spl_object_id($entity)] = $entity;
            }
        } while ($deferred);

        return array_values($resolved ?? []);
    }

    /**
     * @inheritDoc
     */
    public function getDeferralCheckpoint(): int
    {
        return $this->DeferralCheckpoint;
    }

    /**
     * @inheritDoc
     */
    public function resolveDeferredEntities(
        ?int $fromCheckpoint = null,
        ?string $entityType = null,
        ?int $providerId = null
    ): array {
        $entityTypeId = $entityType === null
            ? null
            : $this->EntityTypeMap[$entityType];

        $resolved = [];
        foreach ($this->DeferredEntities as $provId => $entitiesByTypeId) {
            if ($providerId !== null && $provId !== $providerId) {
                continue;
            }
            foreach ($entitiesByTypeId as $entTypeId => $entities) {
                if ($entityTypeId !== null && $entTypeId !== $entityTypeId) {
                    continue;
                }

                if ($fromCheckpoint !== null) {
                    $_entities = $entities;
                    foreach ($_entities as $entityId => $deferred) {
                        foreach ($deferred as $i => $deferredEntity) {
                            if ($i < $fromCheckpoint) {
                                unset($_entities[$entityId][$i]);
                                if (!$_entities[$entityId]) {
                                    unset($_entities[$entityId]);
                                }
                            }
                        }
                    }
                    if (!$_entities) {
                        continue;
                    }
                    $entities = $_entities;
                }

                /** @var array<int|string,non-empty-array<DeferredEntityInterface<SyncEntityInterface>>> $entities */
                foreach ($entities as $entityId => $deferred) {
                    $deferredEntity = reset($deferred);
                    $resolved[] = $deferredEntity->resolve();
                }
            }
        }

        return $resolved;
    }

    /**
     * @inheritDoc
     */
    public function resolveDeferredRelationships(
        ?int $fromCheckpoint = null,
        ?string $entityType = null,
        ?string $forEntityType = null,
        ?string $forEntityProperty = null,
        ?int $providerId = null
    ): array {
        $entityTypeId = $entityType === null
            ? null
            : $this->EntityTypeMap[$entityType];
        $forEntityTypeId = $forEntityType === null
            ? null
            : $this->EntityTypeMap[$forEntityType];

        $resolved = [];
        foreach ($this->DeferredRelationships as $provId => $relationshipsByEntTypeId) {
            if ($providerId !== null && $provId !== $providerId) {
                continue;
            }
            foreach ($relationshipsByEntTypeId as $entTypeId => $relationshipsByForEntTypeId) {
                if ($entityTypeId !== null && $entTypeId !== $entityTypeId) {
                    continue;
                }
                foreach ($relationshipsByForEntTypeId as $forEntTypeId => $relationshipsByForEntProp) {
                    if ($forEntityTypeId !== null && $forEntTypeId !== $forEntityTypeId) {
                        continue;
                    }
                    foreach ($relationshipsByForEntProp as $forEntProp => $relationshipsByForEntId) {
                        if ($forEntityProperty !== null && $forEntProp !== $forEntityProperty) {
                            continue;
                        }
                        foreach ($relationshipsByForEntId as $forEntId => $relationships) {
                            if ($fromCheckpoint !== null) {
                                $_relationships = $relationships;
                                foreach ($_relationships as $index => $deferred) {
                                    if ($index < $fromCheckpoint) {
                                        unset($_relationships[$index]);
                                    }
                                }
                                if (!$_relationships) {
                                    continue;
                                }
                                $relationships = $_relationships;
                            }

                            foreach ($relationships as $index => $deferred) {
                                $resolved[] = $deferred->resolve();
                                unset($this->DeferredRelationships[$provId][$entTypeId][$forEntTypeId][$forEntProp][$forEntId][$index]);
                            }
                        }
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * @inheritDoc
     */
    public function checkProviderHeartbeats(
        int $ttl = 300,
        bool $failEarly = true,
        SyncProviderInterface ...$providers
    ) {
        $this->check();

        if ($providers) {
            $providers = Arr::unique($providers);
        } elseif ($this->Providers) {
            $providers = $this->Providers;
        } else {
            return $this;
        }

        $failed = [];
        /** @var SyncProviderInterface $provider */
        foreach ($providers as $provider) {
            $id = $provider->getProviderId();
            $name = sprintf('%s [#%d]', $provider->getName(), $id);
            Console::logProgress('Checking', $name);
            try {
                $provider->checkHeartbeat($ttl);
                Console::log('Heartbeat OK:', $name);
            } catch (MethodNotImplementedException $ex) {
                Console::log('Heartbeat check not supported:', $name);
            } catch (SyncProviderBackendUnreachableException $ex) {
                Console::exception($ex, Level::DEBUG, null);
                Console::log('No heartbeat:', $name);
                $failed[] = $provider;
                $this->recordError(
                    SyncError::build()
                        ->errorType(SyncErrorType::BACKEND_UNREACHABLE)
                        ->message('Heartbeat check failed: %s')
                        ->values([[
                            'provider_id' => $id,
                            'provider_class' => get_class($provider),
                            'exception' => get_class($ex),
                            'message' => $ex->getMessage()
                        ]])
                        ->build()
                );
            }
            if ($failEarly && $failed) {
                break;
            }
        }

        if ($failed) {
            throw new SyncProviderHeartbeatCheckFailedException(...$failed);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function recordError(SyncErrorInterface $error, bool $deduplicate = false)
    {
        if ($deduplicate) {
            $key = $this->Errors->keyOf($error);
            if ($key !== null) {
                $this->Errors[$key] = $this->Errors[$key]->count();
                return $this;
            }
        }

        $this->Errors[] = $error;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getErrors(): SyncErrorCollectionInterface
    {
        return clone $this->Errors;
    }

    /**
     * @phpstan-assert !null $this->RunId
     * @phpstan-assert !null $this->RunUuid
     * @phpstan-assert !null $this->NamespacesByPrefix
     * @phpstan-assert !null $this->NamespaceUrisByPrefix
     */
    protected function check()
    {
        if ($this->RunId !== null) {
            return $this;
        }

        if (!$this->isCheckRunning()) {
            return $this->safeCheck();
        }

        $sql = <<<SQL
INSERT INTO _sync_run (run_uuid, run_command, run_arguments_json)
VALUES (
    :run_uuid,
    :run_command,
    :run_arguments_json
  );
SQL;

        $stmt = $this->prepare($sql);
        $stmt->bindValue(':run_uuid', $uuid = Get::binaryUuid(), \SQLITE3_BLOB);
        $stmt->bindValue(':run_command', $this->Command, \SQLITE3_TEXT);
        $stmt->bindValue(':run_arguments_json', Json::stringify($this->Arguments), \SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $id = $this->db()->lastInsertRowID();
        $this->RunId = $id;
        $this->RunUuid = $uuid;
        unset($this->Command, $this->Arguments);

        foreach ($this->DeferredProviders as $provider) {
            $this->registerProvider($provider);
        }
        unset($this->DeferredProviders);

        foreach ($this->DeferredEntityTypes as $entity) {
            $this->registerEntity($entity);
        }
        unset($this->DeferredEntityTypes);

        foreach ($this->DeferredNamespaces as $prefix => [$uri, $namespace, $resolver]) {
            $this->registerNamespace($prefix, $uri, $namespace, $resolver);
        }
        unset($this->DeferredNamespaces);

        return $this->reload();
    }

    /**
     * @return $this
     */
    private function reload()
    {
        $db = $this->db();
        $sql = <<<SQL
SELECT
  entity_namespace_prefix,
  base_uri,
  php_namespace
FROM
  _sync_entity_namespace
ORDER BY
  LENGTH(php_namespace) DESC;
SQL;
        $stmt = $this->prepare($sql);
        $result = $this->execute($stmt);
        $this->NamespacesByPrefix = [];
        $this->NamespaceUrisByPrefix = [];
        while (($row = $result->fetchArray(\SQLITE3_NUM)) !== false) {
            /** @var array{string,string,string} $row */
            $this->NamespacesByPrefix[$row[0]] = Str::lower($row[2]);
            $this->NamespaceUrisByPrefix[$row[0]] = $row[1];
        }
        $result->finalize();
        $stmt->close();

        return $this;
    }

    /**
     * @internal
     */
    public function __destruct()
    {
        $exitStatus = -1;
        if (Err::isLoaded() && Err::isShuttingDown()) {
            $exitStatus = Err::getExitStatus();
        }
        $this->close($exitStatus);
    }
}
