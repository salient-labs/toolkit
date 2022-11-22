<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncError;
use Lkrms\Sync\Support\SyncErrorBuilder;
use Lkrms\Sync\Support\SyncErrorCollection;
use Lkrms\Sync\Support\SyncStore;

/**
 * A facade for \Lkrms\Sync\Support\SyncStore
 *
 * @method static SyncStore load(string $filename = ':memory:', string $command = '', string[] $arguments = []) Load and return an instance of the underlying SyncStore class
 * @method static SyncStore getInstance() Return the underlying SyncStore instance
 * @method static bool isLoaded() Return true if an underlying SyncStore instance has been loaded
 * @method static void unload() Clear the underlying SyncStore instance
 * @method static SyncStore close(?int $exitStatus = 0) Close the database (see {@see SyncStore::close()})
 * @method static SyncStore entityType(string $entity) Register a sync entity type and set its ID unless already registered (see {@see SyncStore::entityType()})
 * @method static SyncStore error(SyncError|SyncErrorBuilder $error, bool $deduplicate = false, bool $toConsole = false) Report an error that occurred during a sync operation (see {@see SyncStore::error()})
 * @method static string|null getEntityTypeNamespace(string $entity, bool $uri = false) Get the namespace of a sync entity type (see {@see SyncStore::getEntityTypeNamespace()})
 * @method static string|null getEntityTypeUri(string $entity, bool $compact = true) Get the canonical URI of a sync entity type (see {@see SyncStore::getEntityTypeUri()})
 * @method static SyncErrorCollection getErrors() See {@see SyncStore::getErrors()}
 * @method static string|null getFilename() Get the filename of the database (see {@see SqliteStore::getFilename()})
 * @method static int getRunId() Get the run ID of the current run (see {@see SyncStore::getRunId()})
 * @method static string getRunUuid(bool $binary = false) Get the UUID of the current run (see {@see SyncStore::getRunUuid()})
 * @method static bool isOpen() Check if a database is open (see {@see SqliteStore::isOpen()})
 * @method static SyncStore namespace(string $prefix, string $uri, string $namespace, bool $reload = true) Register a sync entity namespace (see {@see SyncStore::namespace()})
 * @method static SyncStore provider(ISyncProvider $provider) Register a sync provider and set its provider ID (see {@see SyncStore::provider()})
 *
 * @uses SyncStore
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Sync\Support\SyncStore' 'Lkrms\Facade\Sync'
 */
final class Sync extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return SyncStore::class;
    }
}
