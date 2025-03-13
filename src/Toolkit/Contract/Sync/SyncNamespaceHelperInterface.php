<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

interface SyncNamespaceHelperInterface
{
    /**
     * Get a sync entity's provider interface
     *
     * @param class-string<SyncEntityInterface> $entityType
     * @return class-string<SyncProviderInterface>
     */
    public function getEntityTypeProvider(string $entityType): string;

    /**
     * Get sync entities serviced by a provider interface
     *
     * @param class-string<SyncProviderInterface> $provider
     * @return array<class-string<SyncEntityInterface>>
     */
    public function getProviderEntityTypes(string $provider): array;
}
