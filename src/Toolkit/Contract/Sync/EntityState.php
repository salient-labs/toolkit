<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * @api
 */
interface EntityState
{
    /**
     * The entity is being serialized
     */
    public const SERIALIZING = 2;
}
