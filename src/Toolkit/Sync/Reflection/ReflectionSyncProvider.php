<?php declare(strict_types=1);

namespace Salient\Sync\Reflection;

use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncOperation;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Contract\Sync\SyncStoreInterface;
use Salient\Sync\SyncUtil;
use Salient\Utility\Str;
use Closure;
use ReflectionClass;
use ReflectionException;

/**
 * @template TProvider of SyncProviderInterface
 *
 * @extends ReflectionClass<TProvider>
 */
class ReflectionSyncProvider extends ReflectionClass
{
    use SyncReflectionTrait;

    /** @var array<class-string<SyncProviderInterface>,array<class-string<SyncProviderInterface>>> */
    private static array $Interfaces = [];
    /** @var array<class-string<SyncProviderInterface>,array<class-string<SyncEntityInterface>>> */
    private static array $EntityTypes = [];
    /** @var array<class-string<SyncProviderInterface>,array<string,class-string<SyncEntityInterface>>> */
    private static array $EntityTypeBasenames = [];
    /** @var array<class-string<SyncProviderInterface>,array<string,array{SyncOperation::*,class-string<SyncEntityInterface>}>> */
    private static array $Methods = [];
    /** @var array<class-string<SyncProviderInterface>,array<string,array{SyncOperation::*,class-string<SyncEntityInterface>}>> */
    private static array $MagicMethods = [];
    /** @var array<class-string<SyncProviderInterface>,array<class-string<SyncEntityInterface>,array<SyncOperation::*,(Closure(SyncContextInterface, mixed...): (iterable<SyncEntityInterface>|SyncEntityInterface))|false>>> */
    private static array $Closures = [];
    private SyncStoreInterface $Store;

    /**
     * @param TProvider|class-string<TProvider> $provider
     */
    public function __construct($provider, ?SyncStoreInterface $store = null)
    {
        $this->assertImplements($provider, SyncProviderInterface::class);

        $this->Store = $store
            ?? ($provider instanceof SyncProviderInterface ? $provider->getStore() : null)
            ?? SyncUtil::getStore();

        parent::__construct($provider);
    }

    /**
     * Get names of interfaces that extend SyncProviderInterface
     *
     * @return array<class-string<SyncProviderInterface>>
     */
    public function getSyncProviderInterfaces(): array
    {
        return self::$Interfaces[$this->name] ??=
            array_keys($this->getSyncProviderReflectionInterfaces());
    }

    /**
     * Get interfaces that extend SyncProviderInterface
     *
     * @return array<class-string<SyncProviderInterface>,ReflectionClass<SyncProviderInterface>>
     */
    public function getSyncProviderReflectionInterfaces(): array
    {
        foreach ($this->getInterfaces() as $name => $interface) {
            if ($interface->isSubclassOf(SyncProviderInterface::class)) {
                /** @var class-string<SyncProviderInterface> $name */
                $interfaces[$name] = $interface;
            }
        }
        return $interfaces ?? [];
    }

    /**
     * Get names of entity types serviced via sync provider interfaces
     *
     * @return array<class-string<SyncEntityInterface>>
     */
    public function getSyncProviderEntityTypes(): array
    {
        return self::$EntityTypes[$this->name] ??=
            array_keys($this->getSyncProviderReflectionEntities());
    }

    /**
     * Get an array that maps unambiguous kebab-case basenames to qualified
     * names for entity types serviced via sync provider interfaces
     *
     * @return array<string,class-string<SyncEntityInterface>>
     */
    public function getSyncProviderEntityTypeBasenames(): array
    {
        return self::$EntityTypeBasenames[$this->name] ??=
            $this->doGetSyncProviderEntityTypeBasenames();
    }

    /**
     * @return array<string,class-string<SyncEntityInterface>>
     */
    private function doGetSyncProviderEntityTypeBasenames(): array
    {
        foreach ($this->getSyncProviderReflectionEntities() as $name => $entity) {
            $basename = Str::kebab($entity->getShortName());
            if (isset($basenames[$basename])) {
                $basenames[$basename] = false;
                continue;
            }
            $basenames[$basename] = $name;
        }
        return array_filter($basenames ?? []);
    }

    /**
     * Get entity types serviced via sync provider interfaces
     *
     * @return array<class-string<SyncEntityInterface>,ReflectionSyncEntity<SyncEntityInterface>>
     */
    public function getSyncProviderReflectionEntities(): array
    {
        foreach ($this->getSyncProviderInterfaces() as $interface) {
            foreach (SyncUtil::getProviderEntityTypes($interface, $this->Store) as $entityType) {
                $entity = new ReflectionSyncEntity($entityType);
                $entities[$entity->name] = $entity;
            }
        }
        return $entities ?? [];
    }

    /**
     * Check if the provider services a sync entity type
     *
     * @template T of SyncEntityInterface
     *
     * @param ReflectionSyncEntity<T>|class-string<T>|T $entity
     */
    public function isSyncEntityProvider($entity): bool
    {
        if ($entity instanceof ReflectionSyncEntity) {
            $entity = $entity->name;
        } elseif ($entity instanceof SyncEntityInterface) {
            $entity = get_class($entity);
        }

        $interface = SyncUtil::getEntityTypeProvider($entity, $this->Store);

        return interface_exists($interface)
            && $this->implementsInterface($interface);
    }

    /**
     * Get a closure for a sync operation the provider has implemented via a
     * declared method
     *
     * @template T of SyncEntityInterface
     *
     * @param SyncOperation::* $operation
     * @param ReflectionSyncEntity<T>|class-string<T> $entity
     * @param TProvider $provider
     * @return (Closure(SyncContextInterface, mixed...): (iterable<T>|T))|null
     */
    public function getSyncOperationClosure(int $operation, $entity, SyncProviderInterface $provider): ?Closure
    {
        if (!$entity instanceof ReflectionSyncEntity) {
            $entity = new ReflectionSyncEntity($entity);
        }

        $closure = self::$Closures[$this->name][$entity->name][$operation] ?? null;
        if ($closure === null) {
            $method = $this->getSyncOperationMethod($operation, $entity);
            if ($method !== null) {
                $closure = fn(...$args) => $this->$method(...$args);
            }
            self::$Closures[$this->name][$entity->name][$operation] = $closure ?? false;
        }

        return $closure
            ? $closure->bindTo($provider)
            : null;
    }

    /**
     * Get the name of the method that implements a sync operation if declared
     * by the provider
     *
     * @param SyncOperation::* $operation
     * @param ReflectionSyncEntity<SyncEntityInterface>|class-string<SyncEntityInterface> $entity
     */
    public function getSyncOperationMethod(int $operation, $entity): ?string
    {
        if (!$entity instanceof ReflectionSyncEntity) {
            $entity = new ReflectionSyncEntity($entity);
        }

        if (SyncUtil::isListOperation($operation)) {
            $plural = $entity->getPluralName();
            if ($plural !== null) {
                $methods[] = [
                    SyncOperation::CREATE_LIST => 'create',
                    SyncOperation::READ_LIST => 'get',
                    SyncOperation::UPDATE_LIST => 'update',
                    SyncOperation::DELETE_LIST => 'delete',
                ][$operation] . Str::lower($plural);
            }
        }

        $name = Str::lower($entity->getShortName());
        switch ($operation) {
            case SyncOperation::CREATE:
                $methods[] = 'create' . $name;
                $methods[] = 'create_' . $name;
                break;

            case SyncOperation::READ:
                $methods[] = 'get' . $name;
                $methods[] = 'get_' . $name;
                break;

            case SyncOperation::UPDATE:
                $methods[] = 'update' . $name;
                $methods[] = 'update_' . $name;
                break;

            case SyncOperation::DELETE:
                $methods[] = 'delete' . $name;
                $methods[] = 'delete_' . $name;
                break;

            case SyncOperation::CREATE_LIST:
                $methods[] = 'createlist_' . $name;
                break;

            case SyncOperation::READ_LIST:
                $methods[] = 'getlist_' . $name;
                break;

            case SyncOperation::UPDATE_LIST:
                $methods[] = 'updatelist_' . $name;
                break;

            case SyncOperation::DELETE_LIST:
                $methods[] = 'deletelist_' . $name;
                break;
        }

        $methods = array_intersect_key(
            $this->getSyncOperationMethods(),
            array_flip($methods),
        );

        if (!$methods) {
            return null;
        }

        if (count($methods) > 1) {
            throw new ReflectionException(sprintf(
                '%s has multiple implementations of one operation: %s()',
                $this->name,
                implode('(), ', array_keys($methods)),
            ));
        }

        [, $methodEntity] = reset($methods);
        $method = key($methods);
        if ($methodEntity !== $entity->name) {
            throw new ReflectionException(sprintf(
                '%s::%s() does not operate on %s',
                $this->name,
                $method,
                $entity->name,
            ));
        }

        return $method;
    }

    /**
     * Get declared methods that implement sync operations
     *
     * @return array<string,array{SyncOperation::*,class-string<SyncEntityInterface>}>
     */
    public function getSyncOperationMethods(): array
    {
        return self::$Methods[$this->name] ??=
            $this->filterUniqueSyncOperationMethods(true);
    }

    /**
     * Get methods that are not declared but could be used to implement sync
     * operations via method overloading
     *
     * @return array<string,array{SyncOperation::*,class-string<SyncEntityInterface>}>
     */
    public function getSyncOperationMagicMethods(): array
    {
        return self::$MagicMethods[$this->name] ??=
            $this->filterUniqueSyncOperationMethods(false);
    }

    /**
     * @return array<string,array{SyncOperation::*,class-string<SyncEntityInterface>}>
     */
    private function filterUniqueSyncOperationMethods(bool $visible): array
    {
        foreach ($this->getUniqueSyncOperationMethods() as $method => $operation) {
            if (!($visible xor (
                $this->hasMethod($method)
                && $this->getMethod($method)->isPublic()
            ))) {
                $methods[$method] = $operation;
            }
        }
        return $methods ?? [];
    }

    /**
     * @return array<string,array{SyncOperation::*,class-string<SyncEntityInterface>}>
     */
    private function getUniqueSyncOperationMethods(): array
    {
        foreach ($this->getPossibleSyncOperationMethods() as $method => $operation) {
            if (isset($methods[$method])) {
                $methods[$method] = false;
                continue;
            }
            $methods[$method] = $operation;
        }
        return array_filter($methods ?? []);
    }

    /**
     * @return iterable<string,array{SyncOperation::*,class-string<SyncEntityInterface>}>
     */
    private function getPossibleSyncOperationMethods(): iterable
    {
        foreach ($this->getSyncProviderReflectionEntities() as $entity) {
            $plural = $entity->getPluralName();
            if ($plural !== null) {
                $plural = Str::lower($plural);
                yield from [
                    'create' . $plural => [SyncOperation::CREATE_LIST, $entity->name],
                    'get' . $plural => [SyncOperation::READ_LIST, $entity->name],
                    'update' . $plural => [SyncOperation::UPDATE_LIST, $entity->name],
                    'delete' . $plural => [SyncOperation::DELETE_LIST, $entity->name],
                ];
            }

            $name = Str::lower($entity->getShortName());
            yield from [
                'create' . $name => [SyncOperation::CREATE, $entity->name],
                'create_' . $name => [SyncOperation::CREATE, $entity->name],
                'get' . $name => [SyncOperation::READ, $entity->name],
                'get_' . $name => [SyncOperation::READ, $entity->name],
                'update' . $name => [SyncOperation::UPDATE, $entity->name],
                'update_' . $name => [SyncOperation::UPDATE, $entity->name],
                'delete' . $name => [SyncOperation::DELETE, $entity->name],
                'delete_' . $name => [SyncOperation::DELETE, $entity->name],
                'createlist_' . $name => [SyncOperation::CREATE_LIST, $entity->name],
                'getlist_' . $name => [SyncOperation::READ_LIST, $entity->name],
                'updatelist_' . $name => [SyncOperation::UPDATE_LIST, $entity->name],
                'deletelist_' . $name => [SyncOperation::DELETE_LIST, $entity->name],
            ];
        }
    }
}
