<?php declare(strict_types=1);

namespace Salient\Sync\Support;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Entity\Relatable;
use Salient\Contract\Core\Entity\Treeable;
use Salient\Contract\Core\Provider\Providable;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Sync\HydrationPolicy;
use Salient\Contract\Sync\SyncContextInterface;
use Salient\Contract\Sync\SyncEntityInterface;
use Salient\Contract\Sync\SyncProviderInterface;
use Salient\Core\Facade\Sync;
use Salient\Core\Introspector;
use Salient\Core\IntrospectorKeyTargets;
use Salient\Sync\Reflection\ReflectionSyncProvider;
use Salient\Sync\SyncUtil;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Salient\Utility\Regex;
use Salient\Utility\Str;
use Closure;
use LogicException;

/**
 * Generates closures that perform sync-related operations on a class
 *
 * @template TClass of object
 *
 * @extends Introspector<TClass,SyncProviderInterface,SyncEntityInterface,SyncContextInterface>
 */
final class SyncIntrospector extends Introspector
{
    private const ID_KEY = 0;
    private const PARENT_KEY = 1;
    private const CHILDREN_KEY = 2;
    private const ID_PROPERTY = 'Id';

    /** @var SyncIntrospectionClass<TClass> */
    protected $_Class;

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
            SyncProviderInterface::class,
            SyncEntityInterface::class,
            SyncContextInterface::class,
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
            SyncProviderInterface::class,
            SyncEntityInterface::class,
            SyncContextInterface::class,
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
     * Get a closure that creates SyncProviderInterface-serviced instances of the class
     * from arrays
     *
     * Wraps {@see SyncIntrospector::getCreateSyncEntityFromSignatureClosure()}
     * in a closure that resolves array signatures to closures on-demand.
     *
     * @param bool $strict If `true`, the closure will throw an exception if it
     * receives any data that would be discarded.
     * @return Closure(mixed[], SyncProviderInterface, SyncContextInterface): TClass
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
                SyncProviderInterface $provider,
                SyncContextInterface $context
            ) use ($strict) {
                $keys = array_keys($array);
                $closure = $this->getCreateSyncEntityFromSignatureClosure($keys, $strict);
                return $closure($array, $provider, $context);
            };

        $this->_Class->CreateSyncEntityFromClosures[(int) $strict] = $closure;

        return $closure;
    }

    /**
     * Get a closure that creates SyncProviderInterface-serviced instances of the class
     * from arrays with a given signature
     *
     * @param string[] $keys
     * @param bool $strict If `true`, throw an exception if any data would be
     * discarded.
     * @return Closure(mixed[], SyncProviderInterface, SyncContextInterface): TClass
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
                SyncProviderInterface $provider,
                SyncContextInterface $context
            ) use ($closure, $service) {
                return $closure(
                    $array,
                    $service,
                    $context->getContainer(),
                    $provider,
                    $context,
                    $provider->getDateFormatter(),
                    $context->getParent(),
                );
            };
    }

    /**
     * Get a closure to perform sync operations on behalf of a provider's
     * "magic" method
     *
     * Returns `null` if:
     * - the {@see SyncIntrospector} was not created for a
     *   {@see SyncProviderInterface},
     * - the {@see SyncProviderInterface} class already has `$method`, or
     * - `$method` doesn't resolve to an unambiguous sync operation on a
     *   {@see SyncEntityInterface} class serviced by the
     *   {@see SyncProviderInterface} class
     *
     * @return Closure(SyncContextInterface, mixed...)|null
     */
    public function getMagicSyncOperationClosure(string $method, SyncProviderInterface $provider): ?Closure
    {
        if (!$this->_Class->IsSyncProvider) {
            return null;
        }

        $method = Str::lower($method);
        $closure = $this->_Class->MagicSyncOperationClosures[$method] ?? false;
        // Use strict comparison with `false` because null closures are cached
        if ($closure === false) {
            /** @var class-string<SyncProviderInterface> */
            $class = $this->_Class->Class;
            $operation = (new ReflectionSyncProvider($class))
                ->getSyncOperationMagicMethods()[$method] ?? null;
            if ($operation) {
                $entity = $operation[1];
                $operation = $operation[0];
                $closure =
                    function (SyncContextInterface $ctx, ...$args) use ($entity, $operation) {
                        /** @var SyncProviderInterface $this */
                        return $this->with($entity, $ctx)->run($operation, ...$args);
                    };
            }
            $this->_Class->MagicSyncOperationClosures[$method] = $closure ?: null;
        }

        return $closure ? $closure->bindTo($provider) : null;
    }

    /**
     * @param string[] $keys
     * @return Closure(mixed[], string|null, ContainerInterface, SyncProviderInterface|null, SyncContextInterface|null, DateFormatterInterface|null, Treeable|null): TClass
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
                ?SyncProviderInterface $provider,
                ?SyncContextInterface $context,
                ?DateFormatterInterface $dateFormatter,
                ?Treeable $parent
            ) use ($constructor, $updater, $resolver) {
                /** @var class-string<SyncEntityInterface>|null $service */
                $obj = $constructor($array, $service, $container);
                $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                $obj = $resolver($array, $service, $obj, $provider, $context);
                if ($obj instanceof Providable) {
                    $obj->postLoad();
                }
                return $obj;
            };
        } else {
            /** @var class-string<TClass&SyncEntityInterface> */
            $entityType = $this->_Class->Class;
            $closure = static function (
                array $array,
                ?string $service,
                ContainerInterface $container,
                ?SyncProviderInterface $provider,
                ?SyncContextInterface $context,
                ?DateFormatterInterface $dateFormatter,
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

                /** @var class-string<SyncEntityInterface>|null $service */
                if ($id === null || !$provider) {
                    $obj = $constructor($array, $service, $container);
                    $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                    $obj = $resolver($array, $service, $obj, $provider, $context);
                    if ($obj instanceof Providable) {
                        $obj->postLoad();
                    }
                    return $obj;
                }

                $store = $provider->getStore()->registerEntityType($service ?? $entityType);
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
                /** @var TClass&SyncEntityInterface */
                $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                $store->setEntity($providerId, $service ?? $entityType, $id, $obj);
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
        if ($this->_Class->IsSyncEntity
            && ($this->_Class->OneToOneRelationships
                || $this->_Class->OneToManyRelationships)) {
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
                    if (!is_a($relationship, SyncEntityInterface::class, true)) {
                        throw new LogicException(sprintf(
                            '%s does not implement %s',
                            $relationship,
                            SyncEntityInterface::class,
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
                    if (!is_a($relationship, SyncEntityInterface::class, true)) {
                        throw new LogicException(sprintf(
                            '%s does not implement %s',
                            $relationship,
                            SyncEntityInterface::class,
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
            if (!Regex::match('/^(.+)(?:_|\b|(?<=[[:lower:]])(?=[[:upper:]]))id(s?)$/i', $key, $matches)) {
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

            if ($relationship !== null
                    && !is_a($relationship, SyncEntityInterface::class, true)) {
                throw new LogicException(sprintf(
                    '%s does not implement %s',
                    $relationship,
                    SyncEntityInterface::class,
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
     * @param class-string<SyncEntityInterface&Relatable>|null $relationship
     * @return Closure(mixed[], ?string, TClass, ?SyncProviderInterface, ?SyncContextInterface): void
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
                ?SyncProviderInterface $provider,
                ?SyncContextInterface $context
            ) use (
                $key,
                $isList,
                $relationship,
                $property,
                $isParent,
                $isChildren
            ): void {
                if (
                    $data[$key] === null
                    || (Arr::isList($data[$key]) xor $isList)
                    || !$entity instanceof SyncEntityInterface
                    || !$provider instanceof SyncProviderInterface
                    || !$context instanceof SyncContextInterface
                ) {
                    $entity->{$property} = $data[$key];
                    return;
                }

                if ($isList) {
                    if (is_scalar($data[$key][0])) {
                        if (!$isChildren) {
                            DeferredEntity::deferList(
                                $provider,
                                $context->pushEntity($entity, true),
                                $relationship,
                                $data[$key],
                                $entity->{$property},
                            );
                            return;
                        }

                        /** @var SyncEntityInterface&Treeable $entity */
                        /** @disregard P1008 */
                        DeferredEntity::deferList(
                            $provider,
                            $context->pushEntity($entity, true),
                            $relationship,
                            $data[$key],
                            $replace,
                            static function ($child) use ($entity): void {
                                /** @var SyncEntityInterface&Treeable $child */
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
                            $context->pushEntity($entity),
                        )->toArray();

                    if (!$isChildren) {
                        $entity->{$property} = $entities;
                        return;
                    }

                    /** @var array<SyncEntityInterface&Treeable> $entities */
                    foreach ($entities as $child) {
                        /** @var SyncEntityInterface&Treeable $entity */
                        $entity->addChild($child);
                    }
                    return;
                }

                if (is_scalar($data[$key])) {
                    if (!$isParent) {
                        DeferredEntity::defer(
                            $provider,
                            $context->pushEntity($entity, true),
                            $relationship,
                            $data[$key],
                            $entity->{$property},
                        );
                        return;
                    }

                    /** @var SyncEntityInterface&Treeable $entity */
                    /** @disregard P1008 */
                    DeferredEntity::defer(
                        $provider,
                        $context->pushEntity($entity, true),
                        $relationship,
                        $data[$key],
                        $replace,
                        static function ($parent) use ($entity): void {
                            /** @var SyncEntityInterface&Treeable $parent */
                            $entity->setParent($parent);
                        },
                    );
                    return;
                }

                $related =
                    $relationship::provide(
                        $data[$key],
                        $provider,
                        $context->pushEntity($entity),
                    );

                if (!$isParent) {
                    $entity->{$property} = $related;
                    return;
                }

                /**
                 * @var SyncEntityInterface&Treeable $entity
                 * @var SyncEntityInterface&Treeable $related
                 */
                $entity->setParent($related);
            };
    }

    /**
     * @param class-string<SyncEntityInterface&Relatable> $relationship
     * @return Closure(mixed[], ?string, TClass, ?SyncProviderInterface, ?SyncContextInterface): void
     */
    private function getHydrator(
        string $idKey,
        string $relationship,
        string $property,
        ?string $filter,
        bool $isChildren
    ): Closure {
        $entityType = $this->_Class->Class;
        $entityProvider = null;

        return
            static function (
                array $data,
                ?string $service,
                $entity,
                ?SyncProviderInterface $provider,
                ?SyncContextInterface $context
            ) use (
                $idKey,
                $relationship,
                $property,
                $filter,
                $isChildren,
                $entityType,
                &$entityProvider
            ): void {
                if (
                    !$context instanceof SyncContextInterface
                    || !$provider instanceof SyncProviderInterface
                    || !is_a($provider, $entityProvider ??= SyncUtil::getEntityTypeProvider($relationship, SyncUtil::getStore($context->getContainer())))
                    || $data[$idKey] === null
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
                        $context->pushEntity($entity, true),
                        $relationship,
                        $service ?? $entityType,
                        $property,
                        $data[$idKey],
                        $filter,
                        $entity->{$property},
                    );
                    return;
                }

                /** @var SyncEntityInterface&Treeable $entity */
                /** @disregard P1008 */
                DeferredRelationship::defer(
                    $provider,
                    $context->pushEntity($entity, true),
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
                            /** @var SyncEntityInterface&Treeable $child */
                            $entity->addChild($child);
                        }
                    },
                );
            };
    }
}
