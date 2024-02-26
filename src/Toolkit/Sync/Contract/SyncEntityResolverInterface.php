<?php declare(strict_types=1);

namespace Salient\Sync\Contract;

/**
 * Resolves a name to an entity
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
    public function getByName(
        string $name,
        ?float &$uncertainty = null
    ): ?ISyncEntity;
}
