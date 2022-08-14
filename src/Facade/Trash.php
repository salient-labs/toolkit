<?php

declare(strict_types=1);

namespace Lkrms\Facade;

use Lkrms\Concept\Facade;
use Lkrms\Store\TrashStore;

/**
 * A facade for TrashStore
 *
 * @method static TrashStore load(string $filename = ':memory:')
 * @method static $this close()
 * @method static $this empty()
 * @method static ?string getFilename()
 * @method static bool isOpen()
 * @method static $this open(string $filename = ':memory:')
 * @method static $this put(string $type, ?string $key, array|object $object, ?string $deletedFrom, ?int $createdAt = null, ?int $modifiedAt = null)
 *
 * @uses TrashStore
 * @lkrms-generate-command lk-util generate facade --class='Lkrms\Store\TrashStore' --generate='Lkrms\Facade\Trash'
 */
final class Trash extends Facade
{
    protected static function getServiceName(): string
    {
        return TrashStore::class;
    }
}
