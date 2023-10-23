<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Db\DbConnector;
use Lkrms\Facade\Assert;
use Lkrms\Facade\Cache;
use Lkrms\Facade\Compute;
use Lkrms\Support\SqlQuery;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncProviderBackendUnreachableException;
use Lkrms\Sync\Support\DbSyncDefinition;
use Lkrms\Sync\Support\DbSyncDefinitionBuilder;
use ADOConnection;
use ADODB_Exception;
use RuntimeException;

/**
 * Base class for providers with traditional database backends
 */
abstract class DbSyncProvider extends SyncProvider
{
    private DbConnector $DbConnector;
    private ADOConnection $Db;

    /**
     * Specify how to connect to the backend
     *
     * The {@see DbConnector} returned will be cached for the lifetime of the
     * {@see DbSyncProvider} instance.
     */
    abstract protected function getDbConnector(): DbConnector;

    /**
     * @inheritDoc
     */
    public function getBackendIdentifier(): array
    {
        $connector = $this->dbConnector();
        if ($connector->Dsn) {
            /** @todo Implement DSN parsing */
            throw new RuntimeException('DSN parsing not implemented');
        }

        return array_map(
            fn($value) => strtolower(trim($value)),
            [
                $connector->Hostname ?: '',
                (string) $connector->Port ?: '',
                $connector->Database ?: '',
                $connector->Schema ?: '',
            ]
        );
    }

    /**
     * @inheritDoc
     *
     * @template T of ISyncEntity
     * @param class-string<T> $entity
     * @return ISyncDefinition<T,static>
     */
    final public function getDefinition(string $entity): ISyncDefinition
    {
        /** @var ISyncDefinition<T,static> */
        $def = $this
            ->buildDbDefinition(
                $entity,
                DbSyncDefinition::build()
                    ->entity($entity)
                    ->provider($this)
            )
            ->go();

        return $def;
    }

    /**
     * Get a DbConnector instance to open connections to the backend
     */
    final public function dbConnector(): DbConnector
    {
        return $this->DbConnector
            ?? ($this->DbConnector = $this->getDbConnector());
    }

    /**
     * Get a connection to the backend
     */
    final public function getDb(): ADOConnection
    {
        if (!isset($this->Db)) {
            Assert::localeIsUtf8();

            return $this->Db = $this->dbConnector()->getConnection();
        }

        return $this->Db;
    }

    /**
     * Get a SqlQuery instance to prepare queries for the backend
     */
    final public function getSqlQuery(ADOConnection $db): SqlQuery
    {
        return new SqlQuery(fn(string $name): string => $db->Param($name));
    }

    /**
     * @inheritDoc
     */
    public function checkHeartbeat(int $ttl = 300)
    {
        $key = implode(':', [
            static::class,
            __FUNCTION__,
            Compute::hash(...$this->getBackendIdentifier()),
        ]);

        if (Cache::get($key, $ttl) === false) {
            try {
                $this->dbConnector()->getConnection(5);
            } catch (ADODB_Exception $ex) {
                throw new SyncProviderBackendUnreachableException(
                    $ex->getMessage(),
                    $this,
                    $ex,
                );
            }
            Cache::set($key, true, $ttl);
        }

        return $this;
    }

    /**
     * Surface the provider's implementation of sync operations for an entity
     * via a DbSyncDefinition object
     *
     * Return `$defB` if no sync operations are implemented for the entity.
     *
     * @template T of ISyncEntity
     * @param class-string<T> $entity
     * @param DbSyncDefinitionBuilder<T,static> $defB A definition builder
     * with `entity()` and `provider()` already applied.
     * @return DbSyncDefinitionBuilder<T,static>
     */
    protected function buildDbDefinition(
        string $entity, DbSyncDefinitionBuilder $defB
    ): DbSyncDefinitionBuilder {
        return $defB;
    }
}
