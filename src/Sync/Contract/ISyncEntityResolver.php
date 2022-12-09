<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

use Lkrms\Sync\Concept\SyncEntity;

/**
 * Resolves names to entities
 *
 */
interface ISyncEntityResolver
{
    public function getByName(string $name): ?SyncEntity;
}
