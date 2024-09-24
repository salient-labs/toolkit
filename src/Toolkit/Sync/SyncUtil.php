<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Utility\AbstractUtility;

final class SyncUtil extends AbstractUtility
{
    /**
     * Get the canonical URI of a sync entity type
     *
     * @param class-string<SyncEntityInterface> $entityType
     */
    public static function getEntityTypeUri(
        string $entityType,
        bool $compact = true,
        ?SyncStoreInterface $store = null
    ): string {
        if ($store) {
            return $store->getEntityTypeUri($entityType, $compact);
        }
        return '/' . str_replace('\\', '/', ltrim($entityType, '\\'));
    }
}
