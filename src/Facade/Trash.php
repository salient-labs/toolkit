<?php declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\Concept\SqliteStore;
use Lkrms\Store\TrashStore;

/**
 * A facade for \Lkrms\Store\TrashStore
 *
 * @method static TrashStore load(string $filename = ':memory:') Load and return an instance of the underlying TrashStore class
 * @method static TrashStore getInstance() Get the underlying TrashStore instance
 * @method static bool isLoaded() True if an underlying TrashStore instance has been loaded
 * @method static void unload() Clear the underlying TrashStore instance
 * @method static TrashStore close() Close the database
 * @method static TrashStore empty() Delete everything
 * @method static string|null getFilename() Get the filename of the database
 * @method static bool isOpen() Check if a database is open
 * @method static TrashStore open(string $filename = ':memory:') Create or open a storage database
 * @method static TrashStore put(?string $key, array|object $object, ?string $type = null, ?string $deletedFrom = null, ?int $createdAt = null, ?int $modifiedAt = null) Add a deleted object to the store (see {@see TrashStore::put()})
 *
 * @uses TrashStore
 *
 * @extends Facade<TrashStore>
 *
 * @lkrms-generate-command lk-util generate facade 'Lkrms\Store\TrashStore' 'Lkrms\Facade\Trash'
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
