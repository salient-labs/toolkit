<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * @api
 */
interface SyncNamespaceHelperInterface
{
    /**
     * Get a sync entity's provider interface
     *
     * @param class-string<SyncEntityInterface> $entity
     * @return class-string<SyncProviderInterface>
     */
    public function getEntityProvider(string $entity): string;

    /**
     * Get sync entities serviced by a provider interface
     *
     * @param class-string<SyncProviderInterface> $provider
     * @return array<class-string<SyncEntityInterface>>
     */
    public function getProviderEntities(string $provider): array;
}
