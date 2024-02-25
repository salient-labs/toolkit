<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Core\Contract\Readable;
use Salient\Core\Contract\Writable;

/**
 * A generic entity
 */
interface EntityInterface extends
    Normalisable,
    Constructible,
    Readable,
    Writable,
    Extensible,
    Temporal
{
    /**
     * Get the plural form of the entity's class name
     *
     * The return value of `Faculty::plural()`, for example, should be
     * `Faculties`.
     */
    public static function plural(): string;
}
