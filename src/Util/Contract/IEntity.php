<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Salient\Core\Contract\Readable;
use Salient\Core\Contract\Writable;

/**
 * A generic entity
 */
interface IEntity extends
    IResolvable,
    IConstructible,
    Readable,
    Writable,
    IExtensible,
    HasDateProperties
{
    /**
     * Get the plural form of the entity's class name
     *
     * The return value of `Faculty::plural()`, for example, should be
     * `Faculties`.
     */
    public static function plural(): string;
}
