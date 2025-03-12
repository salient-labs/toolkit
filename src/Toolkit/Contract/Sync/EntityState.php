<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

interface EntityState
{
    /**
     * The entity is being serialized
     */
    public const SERIALIZING = 2;
}
