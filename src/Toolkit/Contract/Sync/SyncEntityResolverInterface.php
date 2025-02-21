<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

use Salient\Contract\HasTextComparisonFlag;

/**
 * Resolves a name to an entity
 *
 * @template TEntity of SyncEntityInterface
 */
interface SyncEntityResolverInterface extends HasTextComparisonFlag
{
    /**
     * Resolve a name to an entity
     *
     * @return TEntity|null
     */
    public function getByName(string $name, ?float &$uncertainty = null): ?SyncEntityInterface;
}
