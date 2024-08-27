<?php declare(strict_types=1);

namespace Salient\Sync\Db;

use Salient\Contract\Core\ProviderInterface;
use Salient\Contract\Sync\SyncDefinitionInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Core\Exception\MethodNotImplementedException;
use Salient\Core\Facade\Cache;
use Salient\Core\SqlQuery;
use Salient\Db\DbConnector;
use Salient\Sync\Exception\SyncEntityNotFoundException;
use Salient\Sync\Exception\SyncProviderBackendUnreachableException;
use Salient\Sync\AbstractSyncProvider;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Str;
use ADOConnection;
use ADODB_Exception;

/**
 * Base class for providers with traditional database backends
 */
abstract class DbSyncProvider extends AbstractSyncProvider
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
                ProviderInterface::class
            );
        }

        return Arr::trim([
            Str::lower((string) $connector->Hostname),
            (string) $connector->Port,
            Str::lower((string) $connector->Database),
            Str::lower((string) $connector->Schema),
        ], null, false);
    }

    /**
     * @inheritDoc
     */
    final public function getDefinition(string $entity): SyncDefinitionInterface
    {
        return $this->getDbDefinition($entity);
    }

    /**
     * Override to implement sync operations by returning a DbSyncDefinition
     * object for the given entity
     *
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entity
     * @return DbSyncDefinition<TEntity,$this>
     */
    protected function getDbDefinition(string $entity): DbSyncDefinition
    {
        return $this->builderFor($entity)->build();
    }

    /**
     * Get a new DbSyncDefinitionBuilder for an entity
     *
     * @template TEntity of SyncEntityInterface
     *
     * @param class-string<TEntity> $entity
     * @return DbSyncDefinitionBuilder<TEntity,$this>
     */
    final protected function builderFor(string $entity): DbSyncDefinitionBuilder
    {
        return DbSyncDefinition::build()
            ->entity($entity)
            ->provider($this);
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
            Get::hash(implode("\0", $this->getBackendIdentifier())),
        ]);

        if (Cache::get($key, null, $ttl) === null) {
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
     * @param class-string<SyncEntityInterface> $entity The requested entity.
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
}
