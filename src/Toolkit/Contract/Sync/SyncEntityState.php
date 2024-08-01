<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * Sync entity states
 */
interface SyncEntityState
{
    /**
     * The entity is being serialized
     */
    public const SERIALIZING = 2;
}
