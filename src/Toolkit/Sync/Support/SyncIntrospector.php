<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Container\ContainerInterface;
use Salient\Core\Catalog\Regex;
use Salient\Core\Contract\Providable;
use Salient\Core\Contract\Relatable;
use Salient\Core\Contract\Treeable;
use Salient\Core\Facade\Sync;
use Salient\Core\Utility\Arr;
use Salient\Core\Utility\Get;
use Salient\Core\Utility\Pcre;
use Salient\Core\Utility\Str;
use Salient\Core\DateFormatter;
use Salient\Core\Introspector;
use Salient\Core\IntrospectorKeyTargets;
use Salient\Sync\Catalog\HydrationPolicy;
use Salient\Sync\Catalog\SyncOperation;
use Salient\Sync\Contract\ISyncContext;
use Salient\Sync\Contract\ISyncEntity;
use Salient\Sync\Contract\ISyncProvider;
use Closure;
use LogicException;

/**
 * Generates closures that perform sync-related operations on a class
 *
 * @property-read string|null $EntityNoun
 * @property-read string|null $EntityPlural Not set if the plural class name is the same as the singular one
 *
 * @template TClass of object
 *
 * @extends Introspector<TClass,ISyncProvider,ISyncEntity,ISyncContext>
 */
final class SyncIntrospector extends Introspector
{
    private const ID_KEY = 0;

    private const PARENT_KEY = 1;

    private const CHILDREN_KEY = 2;

    private const ID_PROPERTY = 'Id';

    /**
     * @var SyncIntrospectionClass<TClass>
     */
    protected $_Class;

    /**
     * Get the name of a sync entity's provider interface
     *
     * @param class-string<ISyncEntity> $entity
     * @return class-string<ISyncProvider>
     */
    public static function entityToProvider(string $entity, ?SyncStore $store = null): string
    {
        if (($store || Sync::isLoaded()) &&
                $resolver = ($store ?: Sync::getInstance())->getNamespaceResolver($entity)) {
            return $resolver::entityToProvider($entity);
        }

        return sprintf(
            '%s\Provider\%sProvider',
            Get::namespace($entity),
            Get::basename($entity)
        );
    }

    /**
     * Get the names of sync entities serviced by a provider interface
     *
     * @param class-string<ISyncProvider> $provider
     * @return array<class-string<ISyncEntity>>
     */
    public static function providerToEntity(string $provider, ?SyncStore $store = null): array
    {
        if (($store || Sync::isLoaded()) &&
                $resolver = ($store ?: Sync::getInstance())->getNamespaceResolver($provider)) {
            return $resolver::providerToEntity($provider);
        }

        if (Pcre::match(
            '/^(?<namespace>' . Regex::PHP_TYPE . '\\\\)?Provider\\\\'
                . '(?<class>' . Regex::PHP_IDENTIFIER . ')?Provider$/U',
            $provider,
            $matches
        )) {
            return [$matches['namespace'] . $matches['class']];
        }

        return [];
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $service
     * @return static<T>
     */
    public static function getService(ContainerInterface $container, string $service)
    {
        return new static(
            $service,
            $container->getName($service),
            ISyncProvider::class,
            ISyncEntity::class,
            ISyncContext::class,
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @return static<T>
     */
    public static function get(string $class)
    {
        return new static(
            $class,
            $class,
            ISyncProvider::class,
            ISyncEntity::class,
            ISyncContext::class,
        );
    }

    /**
     * @param class-string<TClass> $class
     * @return SyncIntrospectionClass<TClass>
     */
    protected function getIntrospectionClass(string $class): SyncIntrospectionClass
    {
        return new SyncIntrospectionClass($class);
    }

    /**
     * Get a list of ISyncProvider interfaces implemented by the provider
     *
     * @return string[]|null
     */
    public function getSyncProviderInterfaces(): ?array
    {
        $this->assertIsProvider();

        return $this->_Class->SyncProviderInterfaces;
    }

    /**
     * Get a list of ISyncEntity classes serviced by the provider
     *
     * @return string[]|null
     */
    public function getSyncProviderEntities(): ?array
    {
        $this->assertIsProvider();

        return $this->_Class->SyncProviderEntities;
    }

    /**
     * Get an array that maps unambiguous lowercase entity basenames to
     * ISyncEntity classes serviced by the provider
     *
     * @return array<string,class-string<ISyncEntity>>|null
     */
    public function getSyncProviderEntityBasenames(): ?array
    {
        $this->assertIsProvider();

        return $this->_Class->SyncProviderEntityBasenames;
    }

    /**
     * Get a closure that creates ISyncProvider-serviced instances of the class
     * from arrays
     *
     * Wraps {@see SyncIntrospector::getCreateSyncEntityFromSignatureClosure()}
     * in a closure that resolves array signatures to closures on-demand.
     *
     * @param bool $strict If `true`, the closure will throw an exception if it
     * receives any data that would be discarded.
     * @return Closure(mixed[], ISyncProvider, ISyncContext): TClass
     */
    public function getCreateSyncEntityFromClosure(bool $strict = false): Closure
    {
        $closure =
            $this->_Class->CreateSyncEntityFromClosures[(int) $strict]
                ?? null;

        if ($closure) {
            return $closure;
        }

        $closure =
            function (
                array $array,
                ISyncProvider $provider,
                ISyncContext $context
            ) use ($strict) {
                $keys = array_keys($array);
                $closure = $this->getCreateSyncEntityFromSignatureClosure($keys, $strict);
                return $closure($array, $provider, $context);
            };

        $this->_Class->CreateSyncEntityFromClosures[(int) $strict] = $closure;

        return $closure;
    }

    /**
     * Get a closure that creates ISyncProvider-serviced instances of the class
     * from arrays with a given signature
     *
     * @param string[] $keys
     * @param bool $strict If `true`, throw an exception if any data would be
     * discarded.
     * @return Closure(mixed[], ISyncProvider, ISyncContext): TClass
     */
    public function getCreateSyncEntityFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);

        $closure =
            $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) $strict]
                ?? null;

        if (!$closure) {
            $closure = $this->_getCreateFromSignatureSyncClosure($keys, $strict);
            $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) $strict] = $closure;

            // If the closure was created successfully in strict mode, use it
            // for non-strict purposes too
            if ($strict) {
                $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) false] = $closure;
            }
        }

        // Return a closure that injects this introspector's service
        $service = $this->_Service;

        return
            static function (
                array $array,
                ISyncProvider $provider,
                ISyncContext $context
            ) use ($closure, $service) {
                return $closure(
                    $array,
                    $service,
                    $context->container(),
                    $provider,
                    $context,
                    $provider->dateFormatter(),
                    $context->getParent(),
                );
            };
    }

    /**
     * Get the provider method that implements a sync operation for an entity
     *
     * Returns `null` if the provider doesn't implement the given operation via
     * a declared method, otherwise creates a closure for the operation and
     * binds it to `$provider`.
     *
     * @template T of ISyncEntity
     *
     * @param SyncOperation::* $operation
     * @param class-string<T>|static<T> $entity
     * @return (Closure(ISyncContext, mixed...): (iterable<T>|T))|null
     * @throws LogicException if the {@see SyncIntrospector} and `$entity` don't
     * respectively represent an {@see ISyncProvider} and {@see ISyncEntity}.
     */
    public function getDeclaredSyncOperationClosure($operation, $entity, ISyncProvider $provider): ?Closure
    {
        if (!($entity instanceof SyncIntrospector)) {
            $entity = static::get($entity);
        }

        $_entity = $entity->_Class;
        $closure = $this->_Class->DeclaredSyncOperationClosures[$_entity->Class][$operation] ?? false;

        // Use strict comparison with `false` because null closures are cached
        if ($closure === false) {
            $this->assertIsProvider();

            if (!$_entity->IsSyncEntity) {
                throw new LogicException(
                    sprintf('%s does not implement %s', $_entity->Class, ISyncEntity::class)
                );
            }

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
     * @return Closure(ISyncContext, mixed...)|null
     */
    public function getMagicSyncOperationClosure(string $method, ISyncProvider $provider): ?Closure
    {
        if (!$this->_Class->IsSyncProvider) {
            return null;
        }

        $method = Str::lower($method);
        $closure = $this->_Class->MagicSyncOperationClosures[$method] ?? false;
        // Use strict comparison with `false` because null closures are cached
        if ($closure === false) {
            $operation = $this->_Class->SyncOperationMagicMethods[$method] ?? null;
            if ($operation) {
                $entity = $operation[1];
                $operation = $operation[0];
                $closure =
                    function (ISyncContext $ctx, ...$args) use ($entity, $operation) {
                        /** @var ISyncProvider $this */
                        return $this->with($entity, $ctx)->run($operation, ...$args);
                    };
            }
            $this->_Class->MagicSyncOperationClosures[$method] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    /**
     * @param string[] $keys
     * @return Closure(mixed[], string|null, ContainerInterface, ISyncProvider|null, ISyncContext|null, DateFormatter|null, Treeable|null): TClass
     */
    private function _getCreateFromSignatureSyncClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);

        $closure =
            $this->_Class->CreateFromSignatureSyncClosures[$sig]
                ?? null;

        if ($closure) {
            return $closure;
        }

        $targets = $this->getKeyTargets($keys, true, $strict);
        $constructor = $this->_getConstructor($targets);
        $updater = $this->_getUpdater($targets);
        $resolver = $this->_getResolver($targets);
        $idKey = $targets->CustomKeys[self::ID_KEY] ?? null;

        $updateTargets = $this->getKeyTargets($keys, false, $strict);
        $existingUpdater = $this->_getUpdater($updateTargets);
        $existingResolver = $this->_getResolver($updateTargets);

        if ($idKey === null) {
            $closure = static function (
                array $array,
                ?string $service,
                ContainerInterface $container,
                ?ISyncProvider $provider,
                ?ISyncContext $context,
                ?DateFormatter $dateFormatter,
                ?Treeable $parent
            ) use ($constructor, $updater, $resolver) {
                $obj = $constructor($array, $service, $container);
                $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                $obj = $resolver($array, $service, $obj, $provider, $context);
                if ($obj instanceof Providable) {
                    $obj->postLoad();
                }
                return $obj;
            };
        } else {
            $entityType = $this->_Class->Class;
            $closure = static function (
                array $array,
                ?string $service,
                ContainerInterface $container,
                ?ISyncProvider $provider,
                ?ISyncContext $context,
                ?DateFormatter $dateFormatter,
                ?Treeable $parent
            ) use (
                $constructor,
                $updater,
                $resolver,
                $existingUpdater,
                $existingResolver,
                $idKey,
                $entityType
            ) {
                $id = $array[$idKey];

                if ($id === null || !$provider) {
                    $obj = $constructor($array, $service, $container);
                    $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                    $obj = $resolver($array, $service, $obj, $provider, $context);
                    if ($obj instanceof Providable) {
                        $obj->postLoad();
                    }
                    return $obj;
                }

                $store = $provider->store()->entityType($service ?? $entityType);
                $providerId = $provider->getProviderId();
                $obj = $store->getEntity($providerId, $service ?? $entityType, $id, $context->getOffline());

                if ($obj) {
                    $obj = $existingUpdater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                    $obj = $existingResolver($array, $service, $obj, $provider, $context);
                    if ($obj instanceof Providable) {
                        $obj->postLoad();
                    }
                    return $obj;
                }

                $obj = $constructor($array, $service, $container);
                $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                $store->entity($providerId, $service ?? $entityType, $id, $obj);
                $obj = $resolver($array, $service, $obj, $provider, $context);
                if ($obj instanceof Providable) {
                    $obj->postLoad();
                }
                return $obj;
            };
        }

        $this->_Class->CreateFromSignatureSyncClosures[$sig] = $closure;
        return $closure;
    }

    protected function getKeyTargets(
        array $keys,
        bool $forNewInstance,
        bool $strict,
        bool $normalised = false,
        array $customKeys = [],
        array $keyClosures = []
    ): IntrospectorKeyTargets {
        /** @var array<string,string> Normalised key => original key */
        $keys =
            $this->_Class->Normaliser
                ? array_combine(array_map($this->_Class->CarefulNormaliser, $keys), $keys)
                : array_combine($keys, $keys);

        foreach ([
            self::ID_KEY => self::ID_PROPERTY,
            self::PARENT_KEY => $this->_Class->ParentProperty,
            self::CHILDREN_KEY => $this->_Class->ChildrenProperty,
        ] as $key => $property) {
            if ($property === null) {
                continue;
            }

            if ($key === self::ID_KEY) {
                $property =
                    $this->_Class->Normaliser
                        ? ($this->_Class->CarefulNormaliser)($property)
                        : $property;
            }

            // If receiving values for this property, add the relevant key to
            // $customKeys
            $customKey = $keys[$property] ?? null;
            if ($customKey !== null) {
                $customKeys[$key] = $customKey;
            }
        }

        $idKey = $customKeys[self::ID_KEY] ?? null;

        // Check for relationships to honour by applying deferred entities
        // instead of raw data
        if ($this->_Class->IsSyncEntity &&
            ($this->_Class->OneToOneRelationships ||
                $this->_Class->OneToManyRelationships)) {
            $missing = null;
            foreach ([
                $this->_Class->OneToOneRelationships,
                $this->_Class->OneToManyRelationships,
            ] as $list => $relationships) {
                if ($list) {
                    $missing = array_diff_key($relationships, $keys);
                }
                $relationships = array_intersect_key($relationships, $keys);

                if (!$relationships) {
                    continue;
                }

                foreach ($relationships as $match => $relationship) {
                    if (!is_a($relationship, ISyncEntity::class, true)) {
                        throw new LogicException(sprintf(
                            '%s does not implement %s',
                            $relationship,
                            ISyncEntity::class,
                        ));
                    }

                    $key = $keys[$match];
                    $list = (bool) $list;
                    $isParent = $match === $this->_Class->ParentProperty;
                    $isChildren = $match === $this->_Class->ChildrenProperty;
                    // If $match doesn't resolve to a declared property, it will
                    // resolve to a magic method
                    $property = $this->_Class->Properties[$match] ?? $match;
                    $keyClosures[$match] = $this->getRelationshipClosure(
                        $key,
                        $list,
                        $relationship,
                        $property,
                        $isParent,
                        $isChildren,
                    );
                }
            }

            // Check for absent one-to-many relationships to hydrate
            if ($missing && $idKey !== null && $forNewInstance) {
                foreach ($missing as $key => $relationship) {
                    if (!is_a($relationship, ISyncEntity::class, true)) {
                        throw new LogicException(sprintf(
                            '%s does not implement %s',
                            $relationship,
                            ISyncEntity::class,
                        ));
                    }

                    $isChildren = $key === $this->_Class->ChildrenProperty;
                    $filter =
                        $isChildren
                            ? $this->_Class->ParentProperty
                            : null;
                    $property = $this->_Class->Properties[$key] ?? $key;
                    $keyClosures[$key] = $this->getHydrator(
                        $idKey,
                        $relationship,
                        $property,
                        $filter,
                        $isChildren,
                    );
                }
            }
        }

        // Get keys left behind by constructor parameters, declared properties
        // and magic methods
        $unclaimed = array_diff_key(
            $keys,
            $this->_Class->Parameters,
            array_flip($this->_Class->NormalisedKeys),
        );

        if (!$unclaimed) {
            return parent::getKeyTargets(
                $keys,
                $forNewInstance,
                $strict,
                true,
                $customKeys,
                $keyClosures,
            );
        }

        // Check for any that end with `_id`, `_ids` or similar that would match
        // a property or magic method otherwise
        foreach ($unclaimed as $normalisedKey => $key) {
            if (!Pcre::match('/^(.+)(?:_|\b|(?<=[[:lower:]])(?=[[:upper:]]))id(s?)$/i', $key, $matches)) {
                continue;
            }

            $match =
                $this->_Class->Normaliser
                    ? ($this->_Class->CarefulNormaliser)($matches[1])
                    : $matches[1];

            // Don't use the same key twice
            if (isset($keys[$match]) || isset($keyClosures[$match])) {
                continue;
            }

            if (!in_array($match, $this->_Class->NormalisedKeys, true)) {
                continue;
            }

            // Require a list of values if the key is plural (`_ids` as opposed
            // to `_id`)
            $list = $matches[2] !== '';

            // Check the property or magic method for a relationship to honour
            // by applying deferred entities instead of raw data
            $relationship =
                $this->_Class->IsSyncEntity && $this->_Class->IsRelatable
                    ? ($list
                        ? ($this->_Class->OneToManyRelationships[$match] ?? null)
                        : ($this->_Class->OneToOneRelationships[$match] ?? null))
                    : null;

            if ($relationship !== null &&
                    !is_a($relationship, ISyncEntity::class, true)) {
                throw new LogicException(sprintf(
                    '%s does not implement %s',
                    $relationship,
                    ISyncEntity::class,
                ));
            }

            // As above, if $match doesn't resolve to a declared property, it
            // will resolve to a magic method
            $property = $this->_Class->Properties[$match] ?? $match;
            $isParent = $match === $this->_Class->ParentProperty;
            $isChildren = $match === $this->_Class->ChildrenProperty;
            $keyClosures[$match] = $this->getRelationshipClosure(
                $key,
                $list,
                $relationship,
                $property,
                $isParent,
                $isChildren,
            );

            // Prevent duplication of the key as a meta value
            unset($keys[$normalisedKey]);
        }

        return parent::getKeyTargets(
            $keys,
            $forNewInstance,
            $strict,
            true,
            $customKeys,
            $keyClosures,
        );
    }

    /**
     * @param class-string<ISyncEntity&Relatable>|null $relationship
     * @return Closure(mixed[], ?string, TClass, ?ISyncProvider, ?ISyncContext): void
     */
    private function getRelationshipClosure(
        string $key,
        bool $isList,
        ?string $relationship,
        string $property,
        bool $isParent,
        bool $isChildren
    ): Closure {
        if ($relationship === null) {
            return
                static function (
                    array $data,
                    ?string $service,
                    $entity
                ) use ($key, $property): void {
                    $entity->{$property} = $data[$key];
                };
        }

        return
            static function (
                array $data,
                ?string $service,
                $entity,
                ?ISyncProvider $provider,
                ?ISyncContext $context
            ) use (
                $key,
                $isList,
                $relationship,
                $property,
                $isParent,
                $isChildren
            ): void {
                if (
                    $data[$key] === null ||
                    (Arr::isList($data[$key]) xor $isList) ||
                    !($entity instanceof ISyncEntity) ||
                    !($provider instanceof ISyncProvider) ||
                    !($context instanceof ISyncContext)
                ) {
                    $entity->{$property} = $data[$key];
                    return;
                }

                if ($isList) {
                    if (is_scalar($data[$key][0])) {
                        if (!$isChildren) {
                            DeferredEntity::deferList(
                                $provider,
                                $context->pushWithRecursionCheck($entity),
                                $relationship,
                                $data[$key],
                                $entity->{$property},
                            );
                            return;
                        }

                        /** @var ISyncEntity&Treeable $entity */
                        DeferredEntity::deferList(
                            $provider,
                            $context->pushWithRecursionCheck($entity),
                            $relationship,
                            $data[$key],
                            $replace,
                            static function ($child) use ($entity): void {
                                /** @var ISyncEntity&Treeable $child */
                                $entity->addChild($child);
                            },
                        );
                        return;
                    }

                    $entities =
                        $relationship::provideList(
                            $data[$key],
                            $provider,
                            $context->getConformity(),
                            $context->push($entity),
                        )->toArray();

                    if (!$isChildren) {
                        $entity->{$property} = $entities;
                        return;
                    }

                    /** @var array<ISyncEntity&Treeable> $entities */
                    foreach ($entities as $child) {
                        /** @var ISyncEntity&Treeable $entity */
                        $entity->addChild($child);
                    }
                    return;
                }

                if (is_scalar($data[$key])) {
                    if (!$isParent) {
                        DeferredEntity::defer(
                            $provider,
                            $context->pushWithRecursionCheck($entity),
                            $relationship,
                            $data[$key],
                            $entity->{$property},
                        );
                        return;
                    }

                    /** @var ISyncEntity&Treeable $entity */
                    DeferredEntity::defer(
                        $provider,
                        $context->pushWithRecursionCheck($entity),
                        $relationship,
                        $data[$key],
                        $replace,
                        static function ($parent) use ($entity): void {
                            /** @var ISyncEntity&Treeable $parent */
                            $entity->setParent($parent);
                        },
                    );
                    return;
                }

                $related =
                    $relationship::provide(
                        $data[$key],
                        $provider,
                        $context->push($entity),
                    );

                if (!$isParent) {
                    $entity->{$property} = $related;
                    return;
                }

                /**
                 * @var ISyncEntity&Treeable $entity
                 * @var ISyncEntity&Treeable $related
                 */
                $entity->setParent($related);
            };
    }

    /**
     * @param class-string<ISyncEntity&Relatable> $relationship
     * @return Closure(mixed[], ?string, TClass, ?ISyncProvider, ?ISyncContext): void
     */
    private function getHydrator(
        string $idKey,
        string $relationship,
        string $property,
        ?string $filter,
        bool $isChildren
    ): Closure {
        $entityType = $this->_Class->Class;
        $entityProvider = self::entityToProvider($relationship);

        return
            static function (
                array $data,
                ?string $service,
                $entity,
                ?ISyncProvider $provider,
                ?ISyncContext $context
            ) use (
                $idKey,
                $relationship,
                $property,
                $filter,
                $isChildren,
                $entityType,
                $entityProvider
            ): void {
                if (
                    !($context instanceof ISyncContext) ||
                    !($provider instanceof ISyncProvider) ||
                    !is_a($provider, $entityProvider) ||
                    $data[$idKey] === null
                ) {
                    return;
                }

                $policy = $context->getHydrationPolicy($relationship);
                if ($policy === HydrationPolicy::SUPPRESS) {
                    return;
                }

                if ($filter !== null) {
                    $filter = [$filter => $data[$idKey]];
                }

                if (!$isChildren) {
                    DeferredRelationship::defer(
                        $provider,
                        $context->pushWithRecursionCheck($entity),
                        $relationship,
                        $service ?? $entityType,
                        $property,
                        $data[$idKey],
                        $filter,
                        $entity->{$property},
                    );
                    return;
                }

                /** @var ISyncEntity&Treeable $entity */
                DeferredRelationship::defer(
                    $provider,
                    $context->pushWithRecursionCheck($entity),
                    $relationship,
                    $service ?? $entityType,
                    $property,
                    $data[$idKey],
                    $filter,
                    $replace,
                    static function ($entities) use ($entity, $property): void {
                        if (!$entities) {
                            $entity->{$property} = [];
                            return;
                        }
                        foreach ($entities as $child) {
                            /** @var ISyncEntity&Treeable $child */
                            $entity->addChild($child);
                        }
                    },
                );
            };
    }

    /**
     * @param SyncOperation::* $operation
     * @param static<ISyncEntity> $entity
     */
    private function getSyncOperationMethod($operation, SyncIntrospector $entity): ?string
    {
        $_entity = $entity->_Class;
        $noun = Str::lower($_entity->EntityNoun);
        $plural = Str::lower($_entity->EntityPlural);
        $methods = [];

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

        $methods = array_intersect_key(
            $this->_Class->SyncOperationMethods,
            array_flip($methods)
        );

        if (count($methods) > 1) {
            throw new LogicException(sprintf(
                'Too many implementations: %s',
                implode(', ', $methods),
            ));
        }

        return reset($methods) ?: null;
    }

    private function assertIsProvider(): void
    {
        if (!$this->_Class->IsSyncProvider) {
            throw new LogicException(
                sprintf('%s does not implement %s', $this->_Class->Class, ISyncProvider::class)
            );
        }
    }
}
