<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Sync\Support\SyncStore;

/**
 * A facade for \Lkrms\Sync\Support\SyncStore
 *
 * @method static SyncStore load(string $filename = ':memory:', string $command = '', array $arguments = []) Load and return an instance of the underlying SyncStore class
 * @method static SyncStore getInstance() Return the underlying SyncStore instance
 * @method static bool isLoaded() Return true if an underlying SyncStore instance has been loaded
 * @method static void unload() Clear the underlying SyncStore instance
 * @method static SyncStore close(?int $exitStatus = 0) Close the database (see {@see SyncStore::close()})
 * @method static string|null getFilename() Get the filename of the database (see {@see SqliteStore::getFilename()})
 * @method static int getRunId() Get the run ID of the current run (see {@see SyncStore::getRunId()})
 * @method static string getRunUuid(bool $binary = false) Get the UUID of the current run (see {@see SyncStore::getRunUuid()})
 * @method static bool isOpen() Check if a database is open (see {@see SqliteStore::isOpen()})
 *
 * @uses SyncStore
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Sync\Support\SyncStore' --generate='Lkrms\Facade\Sync'
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
