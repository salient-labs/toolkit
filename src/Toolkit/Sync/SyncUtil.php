<?php declare(strict_types=1);

namespace Salient\Sync;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Core\Facade\Sync;
use Salient\Utility\AbstractUtility;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Regex;

final class SyncUtil extends AbstractUtility
{
    /**
     * Check if a sync operation is CREATE_LIST, READ_LIST, UPDATE_LIST or
     * DELETE_LIST
     *
     * @param SyncOperation::* $operation
     * @return ($operation is SyncOperation::*_LIST ? true : false)
     */
    public static function isListOperation(int $operation): bool
    {
        return [
            SyncOperation::CREATE_LIST => true,
            SyncOperation::READ_LIST => true,
            SyncOperation::UPDATE_LIST => true,
            SyncOperation::DELETE_LIST => true,
        ][$operation] ?? false;
    }

    /**
     * Check if a sync operation is CREATE, UPDATE, DELETE, CREATE_LIST,
     * UPDATE_LIST or DELETE_LIST
     *
     * @param SyncOperation::* $operation
     * @return ($operation is SyncOperation::READ* ? false : true)
     */
    public static function isWriteOperation(int $operation): bool
    {
        return [
            SyncOperation::CREATE => true,
            SyncOperation::UPDATE => true,
            SyncOperation::DELETE => true,
            SyncOperation::CREATE_LIST => true,
            SyncOperation::UPDATE_LIST => true,
            SyncOperation::DELETE_LIST => true,
        ][$operation] ?? false;
    }

    /**
     * Get the name of a sync entity type's provider interface
     *
     * @param class-string<SyncEntityInterface> $entityType
     * @return class-string<SyncProviderInterface>
     */
    public static function getEntityTypeProvider(
        string $entityType,
        ?SyncStoreInterface $store = null
    ): string {
        if ($store) {
            $helper = $store->getNamespaceHelper($entityType);
            if ($helper) {
                return $helper->getEntityProvider($entityType);
            }
        }

        /** @var class-string<SyncProviderInterface> */
        return Arr::implode('\\', [
            Get::namespace($entityType),
            'Provider',
            Get::basename($entityType) . 'Provider',
        ]);
    }

    /**
     * Get the names of sync entity types serviced by a provider interface
     *
     * @param class-string<SyncProviderInterface> $provider
     * @return array<class-string<SyncEntityInterface>>
     */
    public static function getProviderEntityTypes(
        string $provider,
        ?SyncStoreInterface $store = null
    ): array {
        if ($store) {
            $helper = $store->getNamespaceHelper($provider);
            if ($helper) {
                return $helper->getProviderEntities($provider);
            }
        }

        if (Regex::match(
            '/^\\\\?(?<namespace>(?:[^\\\\]++\\\\)*)Provider\\\\(?<class>[^\\\\]+)Provider$/',
            $provider,
            $matches,
        )) {
            /** @var array<class-string<SyncEntityInterface>> */
            return [$matches['namespace'] . $matches['class']];
        }

        return [];
    }

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

    /**
     * Get an entity store, creating one if necessary
     *
     * If a container with a shared {@see SyncStoreInterface} instance is given,
     * it is returned. Otherwise, {@see Sync::getInstance()} is returned if
     * loaded, or a new {@see SyncStore} instance is created.
     */
    public static function getStore(?ContainerInterface $container = null): SyncStoreInterface
    {
        if ($container && $container->hasInstance(SyncStoreInterface::class)) {
            return $container->get(SyncStoreInterface::class);
        }
        if (Sync::isLoaded()) {
            return Sync::getInstance();
        }
        return new SyncStore();
    }
}
