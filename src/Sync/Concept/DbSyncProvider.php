<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Contract\IProvider;
use Lkrms\Db\DbConnector;
use Lkrms\Exception\MethodNotImplementedException;
use Lkrms\Facade\Cache;
use Lkrms\Support\SqlQuery;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Exception\SyncEntityNotFoundException;
use Lkrms\Sync\Exception\SyncProviderBackendUnreachableException;
use Lkrms\Sync\Support\DbSyncDefinition;
use Lkrms\Sync\Support\DbSyncDefinitionBuilder;
use Lkrms\Utility\Arr;
use Lkrms\Utility\Compute;
use ADOConnection;
use ADODB_Exception;

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

        if ($connector->Dsn !== null) {
            /** @todo Implement DSN parsing */
            throw new MethodNotImplementedException(
                static::class,
                __FUNCTION__,
                IProvider::class
            );
        }

        return Arr::trim([
            strtolower((string) $connector->Hostname),
            (string) $connector->Port,
            strtolower((string) $connector->Database),
            strtolower((string) $connector->Schema),
        ], null, false);
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
        return $this->Db
            ?? ($this->Db = $this->dbConnector()->getConnection());
    }

    /**
     * Get a SqlQuery instance to prepare queries for the backend
     */
    protected function getSqlQuery(ADOConnection $db): SqlQuery
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

        if (Cache::get($key, $ttl) === null) {
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
     * Get the first row in a recordset, or throw a SyncEntityNotFoundException
     *
     * @param array<array<string,mixed>> $rows The recordset retrieved from the
     * backend.
     * @param class-string<ISyncEntity> $entity The requested entity.
     * @param int|string $id The identifier of the requested entity.
     * @return array<string,mixed>
     */
    protected function first(array $rows, string $entity, $id): array
    {
        $row = array_shift($rows);
        if ($row === null) {
            throw new SyncEntityNotFoundException($this, $entity, $id);
        }

        return $row;
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
        string $entity,
        DbSyncDefinitionBuilder $defB
    ): DbSyncDefinitionBuilder {
        return $defB;
    }
}
