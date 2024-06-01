<?php declare(strict_types=1);

namespace Salient\Contract\Sync;

/**
 * Resolves sync entities to provider interfaces, and vice-versa
 *
 * @api
 */
interface SyncClassResolverInterface
{
    /**
     * Get a sync entity's provider interface
     *
     * @param class-string<SyncEntityInterface> $entity
     * @return class-string<SyncProviderInterface>
     */
    public function entityToProvider(string $entity): string;

    /**
     * Get sync entities serviced by a provider interface
     *
     * @param class-string<SyncProviderInterface> $provider
     * @return array<class-string<SyncEntityInterface>>
     */
    public function providerToEntity(string $provider): array;
}
