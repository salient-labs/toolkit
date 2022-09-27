<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\SqliteStore;
use Lkrms\Store\TrashStore;

/**
 * A facade for \Lkrms\Store\TrashStore
 *
 * @method static TrashStore load(string $filename = ':memory:') Load and return an instance of the underlying TrashStore class
 * @method static TrashStore getInstance() Return the underlying TrashStore instance
 * @method static bool isLoaded() Return true if an underlying TrashStore instance has been loaded
 * @method static void unload() Clear the underlying TrashStore instance
 * @method static TrashStore close() If a database is open, close it (see {@see SqliteStore::close()})
 * @method static TrashStore empty() Delete everything (see {@see TrashStore::empty()})
 * @method static string|null getFilename() Get the filename of the database (see {@see SqliteStore::getFilename()})
 * @method static bool isOpen() Check if a database is open (see {@see SqliteStore::isOpen()})
 * @method static TrashStore open(string $filename = ':memory:') Create or open a storage database (see {@see TrashStore::open()})
 * @method static TrashStore put(string $type, ?string $key, array|object $object, ?string $deletedFrom, ?int $createdAt = null, ?int $modifiedAt = null) Add a deleted object to the store (see {@see TrashStore::put()})
 *
 * @uses TrashStore
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Store\TrashStore' --generate='Lkrms\Facade\Trash'
 */
final class Trash extends Facade
{
    /**
     * @internal
     */
    protected static function getServiceName(): string
    {
        return TrashStore::class;
    }
}