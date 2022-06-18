<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

use ADOConnection;
use Lkrms\Db\DbConnector;
use Lkrms\Sync\Provider\SyncProvider;
use Lkrms\Util\Assert;
use RuntimeException;

abstract class DbSyncProvider extends SyncProvider
{
    abstract protected function _getDbConnector(): DbConnector;

    protected function getBackendIdentifier(): array
    {
        $connector = $this->getDbConnector();
        if ($connector->Dsn)
        {
            // TODO: implement DSN parsing
            throw new RuntimeException("DSN parsing not implemented: "
                . static::class . "::" . __FUNCTION__);
        }
        return array_map(fn($value) => strtolower(trim($value)), [
            $connector->Hostname ?: "",
            (string)$connector->Port ?: "",
            $connector->Database ?: "",
            $connector->Schema ?: "",
        ]);
    }

    /**
     * @var DbConnector|null
     */
    private $DbConnector;

    /**
     * @var ADOConnection|null
     */
    private $Db;

    final public function getDbConnector(): DbConnector
    {
        return $this->DbConnector ?: ($this->DbConnector = $this->_getDbConnector());
    }

    final public function getDb(): ADOConnection
    {
        if (!$this->Db)
        {
            Assert::localeIsUtf8();
            return $this->Db = $this->getDbConnector()->getConnection();
        }
        return $this->Db;
    }
}
