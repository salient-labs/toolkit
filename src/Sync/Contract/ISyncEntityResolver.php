<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

/**
 * Resolves names to entities
 *
 */
interface ISyncEntityResolver
{
    public function getByName(string $name): ?ISyncEntity;
}
