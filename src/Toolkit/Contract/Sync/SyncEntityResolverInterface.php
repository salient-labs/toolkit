<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * Resolves a name to an entity
 *
 * @template TEntity of SyncEntityInterface
 */
interface SyncEntityResolverInterface
{
    /**
     * Resolve a name to an entity
     *
     * @return TEntity|null
     */
    public function getByName(
        string $name,
        ?float &$uncertainty = null
    ): ?SyncEntityInterface;
}
