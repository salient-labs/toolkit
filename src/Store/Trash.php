<?php

declare(strict_types=1);

namespace Lkrms\Store;

use Lkrms\Core\Facade;

/**
 * A facade for TrashStore
 *
 * @method static TrashStore load(string $filename)
 * @method static void put(string $type, ?string $key, array|object $object, ?string $deletedFrom, int $createdAt = null, int $modifiedAt = null)
 * @method static void empty()
 *
 * @uses TrashStore
 */
final class Trash extends Facade
{
    protected static function getServiceName(): string
    {
        return TrashStore::class;
    }
}
