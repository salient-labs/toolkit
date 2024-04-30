<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Console\ConsoleMessageType as MessageType;
use Salient\Contract\Core\MessageLevel as Level;
use Salient\Contract\Sync\DeferralPolicy;
use Salient\Contract\Sync\HydrationPolicy;
use Salient\Contract\Sync\SyncClassResolverInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncErrorType;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Exception\LogicException;
use Salient\Core\Exception\MethodNotImplementedException;
use Salient\Core\Facade\Console;
use Salient\Core\Facade\Event;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Inflect;
use Salient\Core\Utility\Json;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Core\AbstractStore;
use Salient\Sync\Event\SyncStoreLoadedEvent;
use Salient\Sync\Exception\SyncProviderBackendUnreachableException;
use Salient\Sync\Exception\SyncProviderHeartbeatCheckFailedException;
use Salient\Sync\Exception\SyncStoreException;
use Salient\Sync\Support\DeferredEntity;
use Salient\Sync\Support\DeferredRelationship;
use Salient\Sync\Support\SyncErrorCollection;
use ReflectionClass;
use SQLite3Result;
use SQLite3Stmt;

/**
 * Tracks the state of entities synced to and from third-party backends in a
 * local SQLite database
 *
 * Creating a {@see SyncStore} instance starts a sync operation run that must be
 * terminated by calling {@see SyncStore::close()}, otherwise a failed run is
 * recorded.
 */
final class SyncStore extends AbstractStore
{
    /**
     * @var bool
     */
    private $ErrorReporting = false;

    /**
     * @var int|null
     */
    private $RunId;

    /**
     * @var string|null
     */
    private $RunUuid;

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
     * @var array<string,class-string<SyncClassResolverInterface>>|null
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
     * @var array<int,array<int,array<int|string,array<DeferredEntity<SyncEntityInterface>>>>>
     */
    private $DeferredEntities = [];

    /**
     * Provider ID => entity type ID => requesting entity type ID => requesting
     * entity property => requesting entity ID => [ deferred relationship ]
     *
     * @var array<int,array<int,array<int,array<string,array<int|string,DeferredRelationship<SyncEntityInterface>[]>>>>>
     */
    private $DeferredRelationships = [];

    /**
     * @var SyncErrorCollection
     */
    private $Errors;

    /**
     * @var int
     */
    private $ErrorCount = 0;

    /**
     * @var int
     */
    private $WarningCount = 0;

    /**
     * Prefix => true
     *
     * @var array<string,true>
     */
    private $RegisteredNamespaces = [];

    /**
     * @var int
     */
    private $DeferralCheckpoint = 0;

    /**
     * @var string|null
     */
    private $Command;

    /**
     * @var string[]|null
     */
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
     * @var array<string,array{string,string,class-string<SyncClassResolverInterface>|null}>
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
        $this->Errors = new SyncErrorCollection();
        $this->Command = $command;
        $this->Arguments = $arguments;

        $this
            ->requireUpsert()
            ->openDb(
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
    public function close(int $exitStatus = 0)
    {
        // Don't start a run now
        if (!$this->isOpen() || $this->RunId === null) {
            return $this->closeDb();
        }

        $db = $this->db();
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

        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':exit_status', $exitStatus, \SQLITE3_INTEGER);
        $stmt->bindValue(':run_uuid', $this->RunUuid, \SQLITE3_BLOB);
        $stmt->bindValue(':error_count', $this->ErrorCount, \SQLITE3_INTEGER);
        $stmt->bindValue(':warning_count', $this->WarningCount, \SQLITE3_INTEGER);
        $stmt->bindValue(':errors_json', Json::stringify($this->Errors), \SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        return $this->closeDb();
    }

    /**
     * Check if a run has started
     *
     * @phpstan-assert-if-true !null $this->RunId
     * @phpstan-assert-if-true !null $this->RunUuid
     * @phpstan-assert-if-true !null $this->NamespacesByPrefix
     * @phpstan-assert-if-true !null $this->NamespaceUrisByPrefix
     */
    public function hasRunId(): bool
    {
        return $this->RunId !== null;
    }

    /**
     * Get the run ID of the current run
     */
    public function getRunId(): int
    {
        $this->check();

        return $this->RunId;
    }

    /**
     * Get the UUID of the current run
     *
     * @param bool $binary If `true`, return 16 bytes of raw binary data,
     * otherwise return a 36-byte hexadecimal representation.
     */
    public function getRunUuid(bool $binary = false): string
    {
        $this->check();

        return $binary
            ? $this->RunUuid
            : Get::uuid($this->RunUuid);
    }

    /**
     * Register a sync provider and set its provider ID
     *
     * If a sync run has started, the provider is registered immediately and its
     * provider ID is passed to {@see SyncProviderInterface::setProviderId()}
     * before {@see SyncStore::provider()} returns. Otherwise, registration is
     * deferred until a sync run starts.
     *
     * @return $this
     */
    public function provider(SyncProviderInterface $provider)
    {
        // Don't start a run just to register a provider
        if (!$this->hasRunId()) {
            $this->DeferredProviders[] = $provider;
            return $this;
        }

        $class = get_class($provider);
        $hash = $this->getProviderHash($provider);

        if (isset($this->ProviderMap[$hash])) {
            throw new LogicException(sprintf(
                'Provider already registered: %s',
                $class,
            ));
        }

        // Update `last_seen` if the provider is already in the database
        $db = $this->db();
        $sql = <<<SQL
INSERT INTO
  _sync_provider (provider_hash, provider_class)
VALUES
  (:provider_hash, :provider_class) ON CONFLICT (provider_hash) DO
UPDATE
SET
  last_seen = CURRENT_TIMESTAMP;
SQL;
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
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
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':provider_hash', $hash, \SQLITE3_BLOB);
        /** @var SQLite3Result */
        $result = $stmt->execute();
        /** @var array{int}|false */
        $row = $result->fetchArray(\SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            throw new SyncStoreException('Error retrieving provider ID');
        }

        $providerId = $row[0];
        $this->Providers[$providerId] = $provider;
        $this->ProviderMap[$hash] = $providerId;
        $provider->setProviderId($providerId);

        return $this;
    }

    /**
     * Get the provider ID of a registered sync provider, starting a run if
     * necessary
     */
    public function getProviderId(SyncProviderInterface $provider): int
    {
        if (!$this->hasRunId()) {
            $this->check();
        }

        $hash = $this->getProviderHash($provider);
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
     * Get a registered sync provider
     */
    public function getProvider(string $hash): ?SyncProviderInterface
    {
        // Don't start a run just to get a provider
        if (!$this->hasRunId()) {
            foreach ($this->DeferredProviders as $provider) {
                if ($this->getProviderHash($provider) === $hash) {
                    return $provider;
                }
            }
            return null;
        }

        $id = $this->ProviderMap[$hash] ?? null;
        if ($id === null) {
            return null;
        }
        return $this->Providers[$id];
    }

    /**
     * Get the stable identifier of a sync provider
     */
    public function getProviderHash(SyncProviderInterface $provider): string
    {
        $class = get_class($provider);
        return Get::binaryHash(implode("\0", [
            $class,
            ...$provider->getBackendIdentifier(),
        ]));
    }

    /**
     * Register a sync entity type and set its ID (unless already registered)
     *
     * For performance reasons, `$entity` is case-sensitive and must exactly
     * match the declared name of the sync entity class.
     *
     * @param class-string<SyncEntityInterface> $entity
     * @return $this
     */
    public function entityType(string $entity)
    {
        // Don't start a run just to register an entity type
        if (!$this->hasRunId()) {
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
        $db = $this->db();
        $sql = <<<SQL
INSERT INTO
  _sync_entity_type (entity_type_class)
VALUES
  (:entity_type_class) ON CONFLICT (entity_type_class) DO
UPDATE
SET
  last_seen = CURRENT_TIMESTAMP;
SQL;
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
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
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':entity_type_class', $entity, \SQLITE3_TEXT);
        /** @var SQLite3Result */
        $result = $stmt->execute();
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
     * Register a sync entity namespace
     *
     * A prefix can only be associated with one namespace per {@see SyncStore}
     * and cannot be changed without resetting its backing database.
     *
     * If a prefix has already been registered, its previous URI and PHP
     * namespace are updated if they differ. This is by design and is intended
     * to facilitate refactoring.
     *
     * @param string $prefix A short alternative to `$uri`. Case-insensitive.
     * Must be unique to the {@see SyncStore}. Must be a scheme name that
     * complies with Section 3.1 of \[RFC3986], i.e. a match for the regular
     * expression `^[a-zA-Z][a-zA-Z0-9+.-]*$`.
     * @param string $uri A globally unique namespace URI.
     * @param string $namespace A fully-qualified PHP namespace.
     * @param class-string<SyncClassResolverInterface>|null $resolver
     * @return $this
     */
    public function namespace(
        string $prefix,
        string $uri,
        string $namespace,
        ?string $resolver = null
    ) {
        $prefix = Str::lower($prefix);
        if (isset($this->RegisteredNamespaces[$prefix])
            || (!$this->hasRunId()
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
            if (!Pcre::match('/^[a-zA-Z][a-zA-Z0-9+.-]*$/', $prefix)) {
                throw new LogicException(sprintf(
                    'Invalid prefix: %s',
                    $prefix,
                ));
            }
            $uri = rtrim($uri, '/') . '/';
            $namespace = trim($namespace, '\\') . '\\';
        }

        // Don't start a run just to register a namespace
        if (!$this->hasRunId()) {
            $this->DeferredNamespaces[$prefix] = [$uri, $namespace, $resolver];
            return $this;
        }

        // Update `last_seen` if the namespace is already in the database
        $db = $this->db();
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
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
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
     * Get the canonical URI of a sync entity type
     *
     * @param class-string<SyncEntityInterface> $entity
     * @return string|null `null` if `$entity` is not in a registered sync
     * entity namespace.
     *
     * @see SyncStore::namespace()
     */
    public function getEntityTypeUri(
        string $entity,
        bool $compact = true
    ): ?string {
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
     * Get the namespace of a sync entity type
     *
     * @param class-string<SyncEntityInterface> $entity
     * @return string|null `null` if `$entity` is not in a registered sync
     * entity namespace.
     *
     * @see SyncStore::namespace()
     */
    public function getEntityTypeNamespace(string $entity): ?string
    {
        return $this->classToNamespace($entity);
    }

    /**
     * Get the class resolver for an entity or provider's namespace
     *
     * @param class-string<SyncEntityInterface|SyncProviderInterface> $class
     * @return class-string<SyncClassResolverInterface>|null
     */
    public function getNamespaceResolver(string $class): ?string
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
     * @param class-string<SyncClassResolverInterface>|null $resolver
     */
    private function classToNamespace(
        string $class,
        ?string &$uri = null,
        ?string &$namespace = null,
        ?string &$resolver = null
    ): ?string {
        $class = ltrim($class, '\\');
        $lower = Str::lower($class);

        // Don't start a run just to resolve a class to a namespace
        if (!$this->hasRunId()) {
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
     * Register a sync entity
     *
     * Sync entities are uniquely identified by provider ID, entity type, and
     * entity ID. They cannot be registered multiple times.
     *
     * @param class-string<SyncEntityInterface> $entityType
     * @param int|string $entityId
     * @return $this
     */
    public function entity(
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
     * Get a previously registered and/or stored sync entity
     *
     * @param class-string<SyncEntityInterface> $entityType
     * @param int|string $entityId
     * @param bool|null $offline If `null` (the default), the local entity store
     * is used if its copy of the entity is sufficiently fresh, or if the
     * provider cannot be reached. If `true`, the local entity store is used
     * unconditionally. If `false`, the local entity store is unconditionally
     * ignored.
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
     * Register a deferred sync entity
     *
     * If an entity with the same provider ID, entity type, and entity ID has
     * already been registered, `$deferred` is resolved immediately, otherwise
     * it is added to the deferred entity queue.
     *
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entityType
     * @param int|string $entityId
     * @param DeferredEntity<TEntity> $deferred
     * @return $this
     */
    public function deferredEntity(
        int $providerId,
        string $entityType,
        $entityId,
        DeferredEntity $deferred
    ) {
        $entityTypeId = $this->EntityTypeMap[$entityType];
        /** @var TEntity|null */
        $entity = $this->Entities[$providerId][$entityTypeId][$entityId] ?? null;
        if ($entity) {
            $deferred->replace($entity);
            return $this;
        }

        // Get the deferral policy of the context within which the entity was
        // deferred
        $context = $deferred->getContext();
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
        ] = $deferred;

        // In `RESOLVE_EARLY` mode, deferred entities are added to
        // `$this->DeferredEntities` for the benefit of `$this->entity()`, which
        // only calls `DeferredEntity::replace()` method on registered instances
        if ($policy === DeferralPolicy::RESOLVE_EARLY) {
            $deferred->resolve();
            return $this;
        }

        return $this;
    }

    /**
     * Register a deferred relationship
     *
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entityType
     * @param class-string<SyncEntityInterface> $forEntityType
     * @param int|string $forEntityId
     * @param DeferredRelationship<TEntity> $deferred
     * @return $this
     */
    public function deferredRelationship(
        int $providerId,
        string $entityType,
        string $forEntityType,
        string $forEntityProperty,
        $forEntityId,
        DeferredRelationship $deferred
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
        $context = $deferred->getContext();
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
            $deferred->resolve();
            return $this;
        }

        $deferredList[$this->DeferralCheckpoint++] = $deferred;
        return $this;
    }

    /**
     * Resolve deferred sync entities and relationships recursively until no
     * deferrals remain
     *
     * @param class-string<SyncEntityInterface>|null $entityType
     * @return SyncEntityInterface[]|null
     */
    public function resolveDeferred(
        ?int $fromCheckpoint = null,
        ?string $entityType = null,
        bool $return = false
    ): ?array {
        $checkpoint = $this->DeferralCheckpoint;
        do {
            // Resolve relationships first because they typically deliver
            // multiple entities per round trip, some of which may be in the
            // deferred entity queue
            $deferred = $this->resolveDeferredRelationships($fromCheckpoint, $entityType);
            if ($deferred) {
                if (!$return) {
                    continue;
                }
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

            $deferred = $this->resolveDeferredEntities($fromCheckpoint, $entityType);
            if (!$deferred || !$return) {
                continue;
            }
            foreach ($deferred as $entity) {
                $resolved[spl_object_id($entity)] = $entity;
            }
        } while ($deferred);

        return $return
            ? array_values($resolved ?? [])
            : null;
    }

    /**
     * Get a checkpoint to delineate between deferred entities and relationships
     * already in their respective queues, and any subsequent deferrals
     *
     * The return value of this method can be used with
     * {@see SyncStore::resolveDeferred()},
     * {@see SyncStore::resolveDeferredEntities()} and
     * {@see SyncStore::resolveDeferredRelationships()} to limit the range of
     * entities to resolve, e.g. to those produced by a particular operation.
     */
    public function getDeferralCheckpoint(): int
    {
        return $this->DeferralCheckpoint;
    }

    /**
     * Resolve deferred sync entities from their respective providers and/or the
     * local entity store
     *
     * @param class-string<SyncEntityInterface>|null $entityType
     * @return SyncEntityInterface[]
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

                /** @var array<int|string,non-empty-array<DeferredEntity<SyncEntityInterface>>> $entities */
                foreach ($entities as $entityId => $deferred) {
                    $deferredEntity = reset($deferred);
                    $resolved[] = $deferredEntity->resolve();
                }
            }
        }

        return $resolved;
    }

    /**
     * Resolve deferred relationships from their respective providers and/or the
     * local entity store
     *
     * @param class-string<SyncEntityInterface>|null $entityType
     * @param class-string<SyncEntityInterface>|null $forEntityType
     * @return array<SyncEntityInterface[]>
     */
    public function resolveDeferredRelationships(
        ?int $fromCheckpoint = null,
        ?string $entityType = null,
        ?string $forEntityType = null,
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
     * Throw an exception if a provider has an unreachable backend
     *
     * If called with no `$providers`, all registered providers are checked.
     *
     * Duplicates are ignored.
     *
     * @return $this
     */
    public function checkHeartbeats(
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
            $name = $provider->name();
            $id = $provider->getProviderId();
            if ($id === null) {
                $name .= ' [unregistered]';
            } else {
                $name .= " [#$id]";
            }
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
                $this->error(
                    SyncError::build()
                        ->errorType(SyncErrorType::BACKEND_UNREACHABLE)
                        ->message('Heartbeat check failed: %s')
                        ->values([[
                            'provider_id' => $id,
                            'provider_class' => get_class($provider),
                            'exception' => get_class($ex),
                            'message' => $ex->getMessage()
                        ]])
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
     * Report sync errors to the console as they occur (disabled by default)
     *
     * @return $this
     */
    public function enableErrorReporting()
    {
        $this->ErrorReporting = true;
        return $this;
    }

    /**
     * Disable sync error reporting
     *
     * @return $this
     */
    public function disableErrorReporting()
    {
        $this->ErrorReporting = false;
        return $this;
    }

    /**
     * Report an error that occurred during a sync operation
     *
     * @param SyncError|SyncErrorBuilder $error
     * @return $this
     */
    public function error($error, bool $deduplicate = false)
    {
        if ($error instanceof SyncErrorBuilder) {
            $error = $error->go();
        }

        $seen = $deduplicate
            ? $this->Errors->get($error)
            : false;

        if ($seen) {
            $seen->count();
            Console::count($error->Level);
            return $this;
        }

        $this->Errors[] = $error;

        switch ($error->Level) {
            case Level::EMERGENCY:
            case Level::ALERT:
            case Level::CRITICAL:
            case Level::ERROR:
                $this->ErrorCount++;
                break;
            case Level::WARNING:
                $this->WarningCount++;
                break;
        }

        if (!$this->ErrorReporting) {
            Console::count($error->Level);
            return $this;
        }

        Console::message(
            $error->Level,
            '[' . SyncErrorType::toName($error->ErrorType) . ']',
            sprintf(
                $error->Message,
                ...Arr::toScalars($error->Values),
            ),
        );

        return $this;
    }

    /**
     * Get sync errors recorded so far
     */
    public function getErrors(): SyncErrorCollection
    {
        return clone $this->Errors;
    }

    /**
     * Report sync errors recorded so far to the console
     *
     * If no sync-related errors or warnings have been recorded, `$successText`
     * is printed with level NOTICE.
     *
     * @return $this
     */
    public function reportErrors(
        string $successText = 'No sync errors recorded'
    ) {
        if (!$this->ErrorCount && !$this->WarningCount) {
            Console::info($successText);
            return $this;
        }

        $level = $this->ErrorCount
            ? Level::ERROR
            : Level::WARNING;

        // Print a message with level ERROR or WARNING as appropriate without
        // Console recording an additional error or warning
        Console::message(
            $level,
            Inflect::format(
                $this->ErrorCount,
                '{{#}} sync {{#:error}}%s recorded:',
                $this->WarningCount
                    ? Inflect::format($this->WarningCount, ' and {{#}} {{#:warning}}')
                    : ''
            ),
            null,
            MessageType::STANDARD,
            null,
            false,
        );

        Console::print(
            $this->Errors->toString(true),
            $level,
            MessageType::UNFORMATTED,
        );

        return $this;
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

        $db = $this->db();
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':run_uuid', $uuid = Get::binaryUuid(), \SQLITE3_BLOB);
        $stmt->bindValue(':run_command', $this->Command, \SQLITE3_TEXT);
        $stmt->bindValue(':run_arguments_json', Json::stringify($this->Arguments), \SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        $id = $db->lastInsertRowID();
        $this->RunId = $id;
        $this->RunUuid = $uuid;
        unset($this->Command, $this->Arguments);

        foreach ($this->DeferredProviders as $provider) {
            $this->provider($provider);
        }
        unset($this->DeferredProviders);

        foreach ($this->DeferredEntityTypes as $entity) {
            $this->entityType($entity);
        }
        unset($this->DeferredEntityTypes);

        foreach ($this->DeferredNamespaces as $prefix => [$uri, $namespace, $resolver]) {
            $this->namespace($prefix, $uri, $namespace, $resolver);
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
        /** @var SQLite3Stmt */
        $stmt = $db->prepare($sql);
        /** @var SQLite3Result */
        $result = $stmt->execute();
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

    public function __destruct()
    {
        // If not closed explicitly, assume something went wrong
        $this->close(1);
    }
}
