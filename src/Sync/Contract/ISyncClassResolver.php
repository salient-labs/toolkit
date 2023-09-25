<?php declare(strict_types=1);

namespace Lkrms\Sync\Contract;

/**
 * Resolves sync entity classes to their respective provider interfaces, and
 * vice-versa
 */
interface ISyncClassResolver
{
    /**
     * Get the name of a sync entity's provider interface
     *
     * @param class-string<ISyncEntity> $entity
     * @return class-string<ISyncProvider>
     */
    public static function entityToProvider(string $entity): string;

    /**
     * Get the name of the sync entity serviced by a provider interface
     *
     * @param class-string<ISyncProvider> $provider
     * @return class-string<ISyncEntity>|null
     */
    public static function providerToEntity(string $provider): ?string;
}
