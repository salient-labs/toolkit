<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

/**
 * Resolves names to entities
 *
 * @template TEntity of ISyncEntity
 */
interface ISyncEntityResolver
{
    /**
     * Resolve a name to an entity
     *
     * @return TEntity|null
     */
    public function getByName(string $name): ?ISyncEntity;
}
