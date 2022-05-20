<?php

declare(strict_types=1);

namespace Lkrms\Sync\Provider;

/**
 * Base interface for SyncEntity providers
 *
 * Implemented by {@see Lkrms\Sync\Provider\SyncProvider}.
 *
 */
interface ISyncProvider
{
    public function getBackendHash(): string;
}
