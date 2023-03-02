<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use ADOConnection;
use Lkrms\Db\DbConnector;
use Lkrms\Facade\Assert;
use Lkrms\Support\SqlQuery;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Support\DbSyncDefinition;
use Lkrms\Sync\Support\DbSyncDefinitionBuilder;
use RuntimeException;
use Throwable;

/**
 * Base class for providers with traditional database backends
 *
 */
abstract class DbSyncProvider extends SyncProvider
{
    /**
     * @var DbConnector|null
     */
    private $DbConnector;

    /**
     * @var ADOConnection|null
     */
    private $Db;

    /**
     * Specify how to connect to the upstream database
     *
     * The {@see DbConnector} returned will be cached for the lifetime of the
     * {@see DbSyncProvider} instance.
     *
     */
    abstract protected function getNewDbConnector(): DbConnector;

    public function getBackendIdentifier(): array
    {
        $connector = $this->getDbConnector();
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
     * Surface the provider's implementation of sync operations for an entity
     * via a DbSyncDefinition object
     *
     * Return `null` if no sync operations are implemented for the entity.
     *
     * @param DbSyncDefinitionBuilder $define A definition builder with
     * `entity()` and `provider()` already applied.
     * @return DbSyncDefinition|DbSyncDefinitionBuilder|null
     */
    protected function getDbDefinition(string $entity, DbSyncDefinitionBuilder $define)
    {
        return null;
    }

    final protected function getDefinition(string $entity): ISyncDefinition
    {
        $builder = DbSyncDefinition::build()
                       ->entity($entity)
                       ->provider($this);
        $def = $this->getDbDefinition($entity, $builder);

        return $def
            ? DbSyncDefinitionBuilder::resolve($def)
            : $builder->go();
    }

    final public function getDbConnector(): DbConnector
    {
        return $this->DbConnector
            ?: ($this->DbConnector = $this->getNewDbConnector());
    }

    final public function getDb(): ADOConnection
    {
        if (!$this->Db) {
            Assert::localeIsUtf8();

            return $this->Db = $this->getDbConnector()->getConnection();
        }

        return $this->Db;
    }

    final public function getSqlQuery(ADOConnection $db): SqlQuery
    {
        return new SqlQuery(fn($name) => $db->Param($name));
    }

    public function checkHeartbeat(int $ttl = 300)
    {
        $connector = $this->getDbConnector();
        try {
            $connector->getConnection();
        } catch (Throwable $ex) {
            throw new RuntimeException(
                "Heartbeat connection to database '{$connector->Name}' failed",
                0,
                $ex
            );
        }

        return $this;
    }
}
