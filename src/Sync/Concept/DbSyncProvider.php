<?php declare(strict_types=1);

namespace Lkrms\Sync\Concept;

use Lkrms\Db\DbConnector;
use Lkrms\Facade\Assert;
use Lkrms\Support\SqlQuery;
use Lkrms\Sync\Concept\SyncProvider;
use Lkrms\Sync\Contract\ISyncDefinition;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Support\DbSyncDefinition;
use Lkrms\Sync\Support\DbSyncDefinitionBuilder;
use ADOConnection;
use RuntimeException;

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

    /**
     * @inheritDoc
     */
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
        $this
            ->getDbConnector()
            ->getConnection(5);

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
        string $entity,
        DbSyncDefinitionBuilder $defB
    ): DbSyncDefinitionBuilder {
        return $defB;
    }
}
