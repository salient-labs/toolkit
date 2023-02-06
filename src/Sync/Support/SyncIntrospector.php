<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Closure;
use Lkrms\Contract\IContainer;
use Lkrms\Facade\Convert;
use Lkrms\Support\Dictionary\Regex;
use Lkrms\Support\Introspector;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Sync\Support\SyncOperation;
use RuntimeException;

/**
 * @property-read string|null $EntityNoun
 * @property-read string|null $EntityPlural Not set if the plural class name is the same the singular one
 *
 * @template TClass of object
 * @extends Introspector<TClass,SyncIntrospectionClass>
 */
final class SyncIntrospector extends Introspector
{
    /**
     * @param class-string<ISyncEntity> $entity
     * @return class-string<ISyncProvider>
     */
    final public static function entityToProvider(string $entity): string
    {
        return sprintf('%s\Provider\%sProvider', Convert::classToNamespace($entity), Convert::classToBasename($entity));
    }

    /**
     * @param class-string<ISyncProvider> $provider
     * @return class-string<ISyncEntity>|null
     */
    final public static function providerToEntity(string $provider): ?string
    {
        if (preg_match('/^(?P<namespace>' . Regex::PHP_TYPE . '\\\\)?Provider\\\\(?P<class>' . Regex::PHP_IDENTIFIER . ')?Provider$/U',
                       $provider,
                       $matches)) {
            return $matches['namespace'] . $matches['class'];
        }

        return null;
    }

    protected function getIntrospectionClass(string $class): SyncIntrospectionClass
    {
        return new SyncIntrospectionClass($class);
    }

    /**
     * Get a list of ISyncProvider interfaces implemented by the provider
     *
     * @return string[]|null
     */
    final public function getSyncProviderInterfaces(): ?array
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        return $this->_Class->SyncProviderInterfaces;
    }

    /**
     * Get a list of ISyncEntity classes serviced by the provider
     *
     * @return string[]|null
     */
    final public function getSyncProviderEntities(): ?array
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        return $this->_Class->SyncProviderEntities;
    }

    /**
     * Get an array that maps unambiguous lowercase entity basenames to
     * ISyncEntity classes serviced by the provider
     *
     * @return array<string,string>|null
     */
    final public function getSyncProviderEntityBasenames(): ?array
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        return $this->_Class->SyncProviderEntityBasenames;
    }

    /**
     * Get a closure to create instances of the class on behalf of an
     * ISyncProvider from arrays with a given signature
     *
     * @param bool $strict If `true`, throw an exception if any data would be
     * discarded.
     * @return Closure(array, ISyncProvider, IContainer|ISyncContext|null=)
     * ```php
     * function (array $array, ISyncProvider $provider, IContainer|ISyncContext|null $context = null)
     * ```
     */
    final public function getCreateSyncEntityFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig     = implode("\0", $keys);
        $closure = $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) $strict] ?? null;
        if (!$closure) {
            $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) $strict] =
                $closure = $this->_getCreateFromSignatureSyncClosure($keys, $strict);

            // If the closure was created successfully in strict mode, cache it
            // for `$strict = false` purposes too
            if ($strict && !($this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) false] ?? null)) {
                $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) false] = $closure;
            }
        }
        $service = $this->_Service;

        return
            static function (array $array, ISyncProvider $provider, $context = null) use ($closure, $service) {
                [$container, $parent] = $context instanceof ISyncContext
                    ? [$context->container(), $context->getParent()]
                    : [$context ?: $provider->container(), null];

                return $closure($container,
                                $array,
                                $provider,
                                $context ?: new SyncContext($container, $parent),
                                $parent,
                                $provider->dateFormatter(),
                                $service);
            };
    }

    /**
     * Get a closure to create instances of the class from arrays on behalf of
     * an ISyncProvider
     *
     * This method is similar to
     * {@see SyncIntrospector::getCreateSyncEntityFromSignatureClosure()}, but
     * it returns a closure that resolves array signatures when called.
     *
     * @param bool $strict If `true`, return a closure that throws an exception
     * if any data would be discarded.
     * @return Closure(array, ISyncProvider, IContainer|ISyncContext|null=)
     * ```php
     * function (array $array, ISyncProvider $provider, IContainer|ISyncContext|null $context = null)
     * ```
     */
    final public function getCreateSyncEntityFromClosure(bool $strict = false): Closure
    {
        if ($closure = $this->_Class->CreateSyncEntityFromClosures[(int) $strict] ?? null) {
            return $closure;
        }

        $closure =
            function (array $array, ISyncProvider $provider, $context = null) use ($strict) {
                $keys = array_keys($array);

                return ($this->getCreateSyncEntityFromSignatureClosure($keys, $strict))($array, $provider, $context);
            };

        return $this->_Class->CreateSyncEntityFromClosures[(int) $strict] = $closure;
    }

    /**
     * Get the SyncProvider method that implements a SyncOperation for an entity
     *
     * Returns `null` if:
     * - the {@see SyncIntrospector} was not created for an
     *   {@see ISyncProvider},
     * - `$entity` was not created for an {@see ISyncEntity}, or
     * - the {@see ISyncProvider} class doesn't implement the given
     *   {@see SyncOperation} via a method
     *
     * @param int $operation A {@see SyncOperation} value.
     * @psalm-param SyncOperation::* $operation
     * @param string|SyncIntrospector $entity
     * @return Closure(SyncContext, mixed...)|null
     * ```php
     * fn(SyncContext $ctx, ...$args)
     * ```
     */
    final public function getDeclaredSyncOperationClosure(int $operation, $entity, ISyncProvider $provider): ?Closure
    {
        if (!($entity instanceof SyncIntrospector)) {
            $entity = static::get($entity);
        }
        $_entity = $entity->_Class;

        if (!$this->_Class->IsProvider || !$_entity->IsEntity) {
            return null;
        }

        $closure = $this->_Class->DeclaredSyncOperationClosures[$_entity->Class][$operation] ?? false;
        // Use strict comparison with `false` because null closures are cached
        if ($closure === false) {
            $method = $this->getSyncOperationMethod($operation, $entity);
            if ($method) {
                $closure = fn(...$args) => $this->$method(...$args);
            }
            $this->_Class->DeclaredSyncOperationClosures[$_entity->Class][$operation] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    /**
     * Get a closure to perform sync operations on behalf of a provider's
     * "magic" method
     *
     * Returns `null` if:
     * - the {@see SyncIntrospector} was not created for an
     *   {@see ISyncProvider},
     * - the {@see ISyncProvider} class has already has `$method`, or
     * - `$method` doesn't resolve to an unambiguous sync operation on an
     *   {@see ISyncEntity} class serviced by the {@see ISyncProvider} class
     *
     * @return Closure(SyncContext, mixed...)|null
     * ```php
     * fn(SyncContext $ctx, ...$args)
     * ```
     */
    final public function getMagicSyncOperationClosure(string $method, ISyncProvider $provider): ?Closure
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        $method  = strtolower($method);
        $closure = $this->_Class->MagicSyncOperationClosures[$method] ?? false;
        // Use strict comparison with `false` because null closures are cached
        if ($closure === false) {
            $operation = $this->_Class->SyncOperationMagicMethods[$method] ?? null;
            if ($operation) {
                [$operation, $entity] = $operation;
                $closure              =
                    function (SyncContext $ctx, ...$args) use ($entity, $operation) {
                        /** @var ISyncProvider $this */
                        return $this->with($entity, $ctx)->run($operation, ...$args);
                    };
            }
            $this->_Class->MagicSyncOperationClosures[$method] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    /**
     * @return Closure(IContainer, array, ISyncProvider|null, ISyncContext|null,
     * IHierarchy|null, DateFormatter|null, string|null)
     * ```php
     * function (IContainer $container, array $array, ?ISyncProvider $provider, ?ISyncContext $context, ?IHierarchy $parent, ?DateFormatter $dateFormatter, ?string $service)
     * ```
     */
    private function _getCreateFromSignatureSyncClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);
        if ($closure = $this->_Class->CreateFromSignatureSyncClosures[$sig] ?? null) {
            return $closure;
        }

        $targets = $this->getKeyTargets($keys, true, $strict);
        [$parameterKeys, $passByRefKeys, $propertyKeys, $methodKeys, $metaKeys, $dateKeys] = [
            $targets->Parameters,
            $targets->PassByRefParameters,
            $targets->Properties,
            $targets->Methods,
            $targets->MetaProperties,
            $targets->DateProperties,
        ];

        // Build the smallest possible chain of closures
        $closure = $parameterKeys
            ? $this->_getConstructor($parameterKeys, $passByRefKeys)
            : $this->_getDefaultConstructor();
        if ($propertyKeys) {
            $closure = $this->_getPropertyClosure($propertyKeys, $closure);
        }
        // Call `setProvider()` and `setContext()` early in case property
        // methods need them
        if ($this->_Class->IsProvidable) {
            $closure = $this->_getProvidableClosure($closure);
        }
        // Ditto for `setParent()`
        if ($this->_Class->IsHierarchy) {
            $closure = $this->_getHierarchyClosure($closure);
        }
        if ($methodKeys) {
            $closure = $this->_getMethodClosure($methodKeys, $closure);
        }
        if ($metaKeys) {
            $closure = $this->_getMetaClosure($metaKeys, $closure);
        }
        if ($dateKeys) {
            $closure = $this->_getDateClosure($dateKeys, $closure);
        }

        return $this->_Class->CreateFromSignatureSyncClosures[$sig] = $closure;
    }

    private function getSyncOperationMethod(int $operation, SyncIntrospector $entity): ?string
    {
        $_entity = $entity->_Class;
        $noun    = strtolower($_entity->EntityNoun);
        $plural  = strtolower($_entity->EntityPlural);

        if ($plural) {
            switch ($operation) {
                case SyncOperation::CREATE_LIST:
                    $methods[] = 'create' . $plural;
                    break;

                case SyncOperation::READ_LIST:
                    $methods[] = 'get' . $plural;
                    break;

                case SyncOperation::UPDATE_LIST:
                    $methods[] = 'update' . $plural;
                    break;

                case SyncOperation::DELETE_LIST:
                    $methods[] = 'delete' . $plural;
                    break;
            }
        }

        switch ($operation) {
            case SyncOperation::CREATE:
                $methods[] = 'create' . $noun;
                $methods[] = 'create_' . $noun;
                break;

            case SyncOperation::READ:
                $methods[] = 'get' . $noun;
                $methods[] = 'get_' . $noun;
                break;

            case SyncOperation::UPDATE:
                $methods[] = 'update' . $noun;
                $methods[] = 'update_' . $noun;
                break;

            case SyncOperation::DELETE:
                $methods[] = 'delete' . $noun;
                $methods[] = 'delete_' . $noun;
                break;

            case SyncOperation::CREATE_LIST:
                $methods[] = 'createlist_' . $noun;
                break;

            case SyncOperation::READ_LIST:
                $methods[] = 'getlist_' . $noun;
                break;

            case SyncOperation::UPDATE_LIST:
                $methods[] = 'updatelist_' . $noun;
                break;

            case SyncOperation::DELETE_LIST:
                $methods[] = 'deletelist_' . $noun;
                break;
        }

        $methods = array_intersect_key($this->_Class->SyncOperationMethods,
                                       array_flip($methods ?? []));
        if (count($methods) > 1) {
            throw new RuntimeException('Too many implementations: ' . implode(', ', $methods));
        }

        return reset($methods) ?: null;
    }
}
