<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Console\Catalog\ConsoleLevel as Level;
use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Facade\Compute;
use Lkrms\Facade\Console;
use Lkrms\Facade\Event;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Sync\Catalog\SyncErrorType;
use Lkrms\Sync\Contract\ISyncClassResolver;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Exception\SyncProviderHeartbeatCheckFailedException;
use Lkrms\Sync\Support\SyncErrorBuilder as ErrorBuilder;
use Lkrms\Utility\Convert;
use LogicException;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Tracks the state of entities synced to and from third-party backends in a
 * local SQLite database
 *
 * Creating a {@see SyncStore} instance starts a sync operation run that must be
 * terminated by calling {@see SyncStore::close()}, otherwise a failed run is
 * recorded.
 *
 */
final class SyncStore extends SqliteStore
{
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
     * @var array<int,ISyncProvider>
     */
    private $Providers = [];

    /**
     * Provider hash => provider
     *
     * @var array<string,ISyncProvider>
     */
    private $ProvidersByHash = [];

    /**
     * Entity class => entity type ID
     *
     * @var array<string,int>
     */
    private $EntityTypes = [];

    /**
     * Prefix => PHP namespace
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
     * @var array<string,class-string<ISyncClassResolver>>|null
     */
    private $NamespaceResolversByPrefix;

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
     * @var ISyncProvider[]
     */
    private $DeferredProviders = [];

    /**
     * Deferred namespace registrations
     *
     * Prefix => [ namespace base URI, PHP namespace, class resolver ]
     *
     * @var array<string,array{string,string,class-string<ISyncClassResolver>|null}>
     */
    private $DeferredNamespaces = [];

    /**
     * @param string $command The canonical name of the command performing sync
     * operations (e.g. a qualified class and/or method name).
     * @param string[] $arguments Arguments passed to the command.
     */
    public function __construct(string $filename = ':memory:', string $command = '', array $arguments = [])
    {
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

        Event::dispatch('sync.store.load', $this);
    }

    /**
     * Terminate the current run and close the database
     *
     */
    public function close(?int $exitStatus = 0)
    {
        if (!$this->isOpen()) {
            return $this;
        }

        // Don't start a run now
        if ($this->RunId === null) {
            return parent::close();
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

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':exit_status', $exitStatus, SQLITE3_INTEGER);
        $stmt->bindValue(':run_uuid', $this->RunUuid, SQLITE3_BLOB);
        $stmt->bindValue(':error_count', $this->ErrorCount, SQLITE3_INTEGER);
        $stmt->bindValue(':warning_count', $this->WarningCount, SQLITE3_INTEGER);
        $stmt->bindValue(':errors_json', json_encode($this->Errors), SQLITE3_TEXT);
        $stmt->execute();
        $stmt->close();

        return parent::close();
    }

    /**
     * Get the run ID of the current run
     *
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

        return $binary ? $this->RunUuid : Convert::uuidToHex($this->RunUuid);
    }

    /**
     * Register a sync provider and set its provider ID
     *
     * If a sync run has started, the provider is registered immediately and its
     * provider ID is passed to {@see ISyncProvider::setProviderId()} before
     * {@see SyncStore::provider()} returns. Otherwise, registration is deferred
     * until a sync run starts.
     *
     * @return $this
     */
    public function provider(ISyncProvider $provider)
    {
        // Don't start a run just to register a provider
        if ($this->RunId === null) {
            $this->DeferredProviders[] = $provider;

            return $this;
        }

        $class = get_class($provider);
        $hash = Compute::binaryHash($class, ...$provider->getBackendIdentifier());

        if (($this->ProvidersByHash[$hash] ?? null) !== null) {
            throw new LogicException("Provider already registered: $class");
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
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':provider_hash', $hash, SQLITE3_BLOB);
        $stmt->bindValue(':provider_class', $class, SQLITE3_TEXT);
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
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':provider_hash', $hash, SQLITE3_BLOB);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            throw new RuntimeException('Error retrieving provider ID');
        }

        $provider->setProviderId($row[0], $hash);
        $this->Providers[$row[0]] = $this->ProvidersByHash[$hash] = $provider;

        return $this;
    }

    /**
     * Register a sync entity type and set its ID (unless already registered)
     *
     * @param class-string<ISyncEntity> $entity
     * @return $this
     */
    public function entityType(string $entity)
    {
        if (($this->EntityTypes[$entity] ?? null) !== null) {
            return $this;
        }

        $class = new ReflectionClass($entity);
        if (!$class->implementsInterface(ISyncEntity::class)) {
            throw new LogicException("Does not implement ISyncEntity: $entity");
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
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':entity_type_class', $class->name, SQLITE3_TEXT);
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
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':entity_type_class', $class->name, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_NUM);
        $stmt->close();

        if ($row === false) {
            throw new RuntimeException('Error retrieving entity type ID');
        }

        $class->getMethod('setEntityTypeId')->invoke(null, $row[0]);
        $this->EntityTypes[$entity] = $row[0];

        return $this;
    }

    /**
     * Register a sync entity namespace
     *
     * A prefix can only be associated with one namespace per {@see SyncStore}
     * and cannot be changed unless the {@see SyncStore}'s backing database has
     * been reset.
     *
     * If `$prefix` has already been registered, its previous URI and PHP
     * namespace are updated if they differ. This is by design and is intended
     * to facilitate refactoring.
     *
     * @param string $prefix A short alternative to `$uri`. Case-insensitive.
     * Must be unique to the {@see SyncStore}. Must be a scheme name that
     * complies with Section 3.1 of [RFC3986], i.e. a match for the regular
     * expression `^[a-zA-Z][a-zA-Z0-9+.-]*$`.
     * @param string $uri A globally unique namespace URI.
     * @param string $namespace A fully-qualified PHP namespace.
     * @param class-string<ISyncClassResolver>|null $resolver
     * @return $this
     */
    public function namespace(string $prefix, string $uri, string $namespace, ?string $resolver = null)
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*$/', $prefix)) {
            throw new LogicException("Invalid prefix: $prefix");
        }

        $prefix = strtolower($prefix);
        if (($this->RegisteredNamespaces[$prefix] ?? false) ||
                ($this->RunId === null && ($this->DeferredNamespaces[$prefix] ?? false))) {
            throw new LogicException("Prefix already registered: $prefix");
        }

        $uri = rtrim($uri, '/') . '/';
        $namespace = trim($namespace, '\\') . '\\';

        // Don't start a run just to register a namespace
        if ($this->RunId === null) {
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
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':entity_namespace_prefix', $prefix, SQLITE3_TEXT);
        $stmt->bindValue(':base_uri', $uri, SQLITE3_TEXT);
        $stmt->bindValue(':php_namespace', $namespace, SQLITE3_TEXT);
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
     * @param class-string<ISyncEntity> $entity
     * @return string|null `null` if `$entity` is not in a registered sync
     * entity namespace.
     * @see SyncStore::namespace()
     */
    public function getEntityTypeUri(string $entity, bool $compact = true): ?string
    {
        if (!($prefix = $this->classToNamespace($entity, $uri, $namespace))) {
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
     * @param class-string<ISyncEntity> $entity
     * @return string|null `null` if `$entity` is not in a registered sync
     * entity namespace.
     * @see SyncStore::namespace()
     */
    public function getEntityTypeNamespace(string $entity): ?string
    {
        return $this->classToNamespace($entity);
    }

    /**
     * Get the class resolver for an entity or provider's namespace
     *
     * @param class-string<ISyncEntity|ISyncProvider> $class
     * @return class-string<ISyncClassResolver>|null
     */
    public function getNamespaceResolver(string $class): ?string
    {
        if (!$this->classToNamespace($class, $uri, $namespace, $resolver)) {
            return null;
        }

        return $resolver;
    }

    /**
     * @param class-string<ISyncEntity|ISyncProvider> $class
     * @param class-string<ISyncClassResolver>|null $resolver
     */
    private function classToNamespace(
        string $class,
        ?string &$uri = null,
        ?string &$namespace = null,
        ?string &$resolver = null
    ): ?string {
        $class = ltrim($class, '\\');
        $lower = strtolower($class);

        // Don't start a run just to resolve a class to a namespace
        if ($this->RunId === null) {
            foreach ($this->DeferredNamespaces as $prefix => [$_uri, $_namespace, $_resolver]) {
                $_namespace = strtolower($_namespace);
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
     * Throw an exception if a provider has an unreachable backend
     *
     * If called with no `$providers`, all registered providers are checked.
     *
     * Duplicates are ignored.
     *
     * @return $this
     */
    public function checkHeartbeats(int $ttl = 300, bool $failEarly = true, ISyncProvider ...$providers)
    {
        $this->check();

        if ($providers) {
            $providers = Convert::toUniqueList($providers);
        } elseif ($this->Providers) {
            $providers = $this->Providers;
        } else {
            return $this;
        }

        $failed = [];
        /** @var ISyncProvider $provider */
        foreach ($providers as $provider) {
            $name = $provider->name() ?: get_class($provider);
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
            } catch (Throwable $ex) {
                Console::exception($ex, Level::DEBUG, null);
                Console::log('No heartbeat:', $name);
                $failed[] = $provider;
                $this->error(
                    ErrorBuilder::build()
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
     * Report an error that occurred during a sync operation
     *
     * @param SyncError|SyncErrorBuilder $error
     * @return $this
     */
    public function error($error, bool $deduplicate = false, bool $toConsole = false)
    {
        $error = ErrorBuilder::resolve($error);
        if (!$deduplicate || !($seen = $this->Errors->get($error))) {
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
        } else {
            /** @var SyncError $seen */
            $seen->count();
        }

        if ($toConsole) {
            $error->toConsole($deduplicate);
        } else {
            Console::count($error->Level);
        }

        return $this;
    }

    /**
     * Get sync operation errors recorded so far
     *
     */
    public function getErrors(): SyncErrorCollection
    {
        return clone $this->Errors;
    }

    protected function check()
    {
        if (($this->RunId) !== null) {
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
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':run_uuid', $uuid = Compute::uuid(true), SQLITE3_BLOB);
        $stmt->bindValue(':run_command', $this->Command, SQLITE3_TEXT);
        $stmt->bindValue(':run_arguments_json', json_encode($this->Arguments), SQLITE3_TEXT);
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
        $stmt = $db->prepare($sql);
        $result = $stmt->execute();
        $this->NamespacesByPrefix = [];
        $this->NamespaceUrisByPrefix = [];
        while (($row = $result->fetchArray(SQLITE3_NUM)) !== false) {
            $this->NamespacesByPrefix[$row[0]] = strtolower($row[2]);
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
