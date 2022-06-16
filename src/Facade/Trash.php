<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\TrashStore;

/**
 * A facade for TrashStore
 *
 * @uses TrashStore
 *
 * @method static TrashStore load(string $filename = ":memory:")
 * @method static void open(string $filename = ":memory:")
 * @method static ?string getFilename()
 * @method static bool isOpen()
 * @method static void close()
 * @method static void put(string $type, ?string $key, array|object $object, ?string $deletedFrom, int $createdAt = null, int $modifiedAt = null)
 * @method static void empty()
 */
final class Trash extends Facade
{
    protected static function getServiceName(): string
    {
        return TrashStore::class;
    }
}
