<?php declare(strict_types=1);

namespace Salient\Sync\Contract;

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
     * Get the names of sync entities serviced by a provider interface
     *
     * @param class-string<ISyncProvider> $provider
     * @return array<class-string<ISyncEntity>>
     */
    public static function providerToEntity(string $provider): array;
}
