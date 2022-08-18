<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\TrashStore;

/**
 * A facade for TrashStore
 *
 * @method static TrashStore load(string $filename = ':memory:') Create and return the underlying TrashStore
 * @method static TrashStore getInstance() Return the underlying TrashStore
 * @method static bool isLoaded() Return true if the underlying TrashStore has been created
 * @method static TrashStore close() If a database is open, close it
 * @method static TrashStore empty() Delete everything
 * @method static string|null getFilename() Get the filename of the database
 * @method static bool isOpen() Check if a database is open
 * @method static TrashStore open(string $filename = ':memory:') Create or open a storage database
 * @method static TrashStore put(string $type, ?string $key, array|object $object, ?string $deletedFrom, ?int $createdAt = null, ?int $modifiedAt = null) Add a deleted object to the store
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
