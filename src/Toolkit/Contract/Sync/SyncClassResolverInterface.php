<?php declare(strict_types=1);

namespace Salient\Sync\Contract;

/**
 * Resolves sync entity classes to their respective provider interfaces, and
 * vice-versa
 */
interface SyncClassResolverInterface
{
    /**
     * Get the name of a sync entity's provider interface
     *
     * @param class-string<SyncEntityInterface> $entity
     * @return class-string<SyncProviderInterface>
     */
    public static function entityToProvider(string $entity): string;

    /**
     * Get the names of sync entities serviced by a provider interface
     *
     * @param class-string<SyncProviderInterface> $provider
     * @return array<class-string<SyncEntityInterface>>
     */
    public static function providerToEntity(string $provider): array;
}
