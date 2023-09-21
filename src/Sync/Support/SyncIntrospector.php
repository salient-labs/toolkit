<?php declare(strict_types=1);

namespace Lkrms\Sync\Support;

use Lkrms\Concern\TIntrospector;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Contract\ITreeable;
use Lkrms\Facade\Sync;
use Lkrms\Support\Catalog\RegularExpression as Regex;
use Lkrms\Support\DateFormatter;
use Lkrms\Support\Introspector;
use Lkrms\Support\IntrospectorKeyTargets;
use Lkrms\Sync\Catalog\SyncOperation;
use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncEntity;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Utility\Convert;
use Lkrms\Utility\Pcre;
use Lkrms\Utility\Test;
use Closure;
use LogicException;

/**
 * @property-read string|null $EntityNoun
 * @property-read string|null $EntityPlural Not set if the plural class name is the same as the singular one
 *
 * @template TClass of object
 * @template TIntrospectionClass of SyncIntrospectionClass
 * @extends Introspector<TClass,TIntrospectionClass<TClass>>
 */
final class SyncIntrospector extends Introspector
{
    /**
     * @use TIntrospector<TClass,TIntrospectionClass<TClass>>
     */
    use TIntrospector;

    private const ID_KEY = 'Id';

    /**
     * @var TIntrospectionClass<TClass>
     * @todo Remove this property when Intelephense resolves trait generics
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
            Convert::classToNamespace($entity),
            Convert::classToBasename($entity)
        );
    }

    /**
     * Get the name of the sync entity serviced by a provider interface
     *
     * @param class-string<ISyncProvider> $provider
     * @return class-string<ISyncEntity>|null
     */
    public static function providerToEntity(string $provider, ?SyncStore $store = null): ?string
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
            return $matches['namespace'] . $matches['class'];
        }

        return null;
    }

    /**
     * @param class-string<TClass> $class
     * @return SyncIntrospectionClass<TClass>
     */
    private function getIntrospectionClass(string $class): SyncIntrospectionClass
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
    public function getSyncProviderEntities(): ?array
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
     * @return array<string,class-string<ISyncEntity>>|null
     */
    public function getSyncProviderEntityBasenames(): ?array
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
     * @param string[] $keys
     * @param bool $strict If `true`, throw an exception if any data would be
     * discarded.
     * @return Closure(mixed[], ISyncProvider, IContainer|ISyncContext|null=): TClass
     */
    public function getCreateSyncEntityFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);
        $closure = $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) $strict] ?? null;
        if (!$closure) {
            $closure = $this->_getCreateFromSignatureSyncClosure($keys, $strict);
            $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) $strict] = $closure;

            // If the closure was created successfully in strict mode, cache it
            // for `$strict = false` purposes too
            if ($strict) {
                $this->_Class->CreateSyncEntityFromSignatureClosures[$sig][(int) false] = $closure;
            }
        }
        $service = $this->_Service;

        return
            static function (array $array, ISyncProvider $provider, $context = null) use ($closure, $service) {
                /** @var IContainer $container */
                [$container, $parent] =
                    $context instanceof ISyncContext
                        ? [$context->container(), $context->getParent()]
                        : [$context ?: $provider->container(), null];

                return $closure(
                    $array,
                    $service,
                    $container,
                    $provider,
                    $context ?: $container->get(SyncContext::class)->withParent($parent),
                    $provider->dateFormatter(),
                    $parent,
                );
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
     * @return Closure(mixed[], ISyncProvider, IContainer|ISyncContext|null=)
     */
    public function getCreateSyncEntityFromClosure(bool $strict = false): Closure
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
     * @template T of ISyncEntity
     * @param SyncOperation::* $operation
     * @param class-string<T>|self<T,TIntrospectionClass<T>> $entity
     * @return Closure(ISyncContext, mixed...)|null
     */
    public function getDeclaredSyncOperationClosure(int $operation, $entity, ISyncProvider $provider): ?Closure
    {
        if (!($entity instanceof SyncIntrospector)) {
            /** @var self<T,TIntrospectionClass<T>> */
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
     * @return Closure(ISyncContext, mixed...)|null
     */
    public function getMagicSyncOperationClosure(string $method, ISyncProvider $provider): ?Closure
    {
        if (!$this->_Class->IsProvider) {
            return null;
        }

        $method = strtolower($method);
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

    protected function getKeyTargets(
        array $keys,
        bool $withParameters,
        bool $strict,
        bool $normalised = false,
        array $customKeys = [],
        array $keyClosures = []
    ): IntrospectorKeyTargets {
        $keys = $this->_Class->Normaliser
            ? array_combine(array_map($this->_Class->CarefulNormaliser, $keys), $keys)
            : array_combine($keys, $keys);

        $normalisedIdKey = $this->_Class->Normaliser
            ? ($this->_Class->CarefulNormaliser)(self::ID_KEY)
            : self::ID_KEY;

        $idKey = $keys[$normalisedIdKey] ?? null;
        if ($idKey !== null) {
            $customKeys = [self::ID_KEY => $idKey];
        }

        // Check for relationships to honour by applying deferred entities
        // instead of raw data
        if ($this->_Class->IsRelatable &&
                ($this->_Class->OneToOneRelationships || $this->_Class->OneToManyRelationships)) {
            foreach ([
                $this->_Class->OneToOneRelationships,
                $this->_Class->OneToManyRelationships,
            ] as $list => $relationships) {
                $relationships = array_intersect_key($relationships, $keys);
                if (!$relationships) {
                    continue;
                }
                foreach ($relationships as $match => $relationship) {
                    if (!is_a($relationship, ISyncEntity::class, true)) {
                        continue;
                    }
                    $key = $keys[$match];
                    $list = (bool) $list;
                    // If $match doesn't resolve to a declared property, it will
                    // resolve to a magic method
                    $property = $this->_Class->Properties[$match] ?? $match;
                    $closures[$match] = static function (
                        array $data,
                        $entity,
                        ?IProvider $provider,
                        ?IProviderContext $context
                    ) use ($key, $list, $relationship, $property): void {
                        if ((Test::isListArray($data[$key]) xor $list) ||
                                !($entity instanceof ISyncEntity) ||
                                !($provider instanceof ISyncProvider) ||
                                !($context instanceof ISyncContext)) {
                            $entity->{$property} = $data[$key];
                            return;
                        }
                        if ($list) {
                            DeferredSyncEntity::deferList($provider, $context->push($entity), $relationship, $data[$key], $entity->{$property});
                            return;
                        }
                        DeferredSyncEntity::defer($provider, $context->push($entity), $relationship, $data[$key], $entity->{$property});
                    };
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
            return parent::getKeyTargets($keys, $withParameters, $strict, true, $customKeys, $closures ?? []);
        }

        // Check for any that end with `_id`, `_ids` or similar that would match
        // a property or magic method otherwise
        foreach ($unclaimed as $normalisedKey => $key) {
            if (!Pcre::match('/^(.+)(?:_|\b|(?<=[[:lower:]])(?=[[:upper:]]))id(s?)$/i', $key, $matches)) {
                continue;
            }
            $match = $this->_Class->Normaliser
                ? ($this->_Class->CarefulNormaliser)($matches[1])
                : $matches[1];
            if (!in_array($match, $this->_Class->NormalisedKeys, true)) {
                continue;
            }
            // Require a list of values if the key is plural (`_ids` as opposed
            // to `_id`)
            $list = (bool) $matches[2];
            // Check the property or magic method for a relationship to honour
            // by applying deferred entities instead of raw data
            $relationship = $list
                ? ($this->_Class->OneToManyRelationships[$match] ?? null)
                : ($this->_Class->OneToOneRelationships[$match] ?? null);
            if ($relationship !== null &&
                    !is_a($relationship, ISyncEntity::class, true)) {
                $relationship = null;
            }
            // As above, if $match doesn't resolve to a declared property, it
            // will resolve to a magic method
            $property = $this->_Class->Properties[$match] ?? $match;
            $closures[$match] = static function (
                array $data,
                $entity,
                ?IProvider $provider,
                ?IProviderContext $context
            ) use ($key, $list, $relationship, $property): void {
                if ($relationship === null ||
                        (Test::isListArray($data[$key]) xor $list) ||
                        !($entity instanceof ISyncEntity) ||
                        !($provider instanceof ISyncProvider) ||
                        !($context instanceof ISyncContext)) {
                    $entity->{$property} = $data[$key];
                    return;
                }
                if ($list) {
                    DeferredSyncEntity::deferList($provider, $context->push($entity), $relationship, $data[$key], $entity->{$property});
                    return;
                }
                DeferredSyncEntity::defer($provider, $context->push($entity), $relationship, $data[$key], $entity->{$property});
            };

            // Prevent duplication of the key as a meta value
            unset($keys[$normalisedKey]);
        }

        return parent::getKeyTargets($keys, $withParameters, $strict, true, $customKeys, $closures ?? []);
    }

    /**
     * @param string[] $keys
     * @return Closure(mixed[], string|null, IContainer, ISyncProvider|null, ISyncContext|null, DateFormatter|null, ITreeable|null): TClass
     */
    private function _getCreateFromSignatureSyncClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);
        if ($closure = $this->_Class->CreateFromSignatureSyncClosures[$sig] ?? null) {
            return $closure;
        }

        $targets = $this->getKeyTargets($keys, true, $strict);
        $constructor = $this->_getConstructor($targets);
        $updater = $this->_getUpdater($targets);
        $idKey = $targets->CustomKeys[self::ID_KEY] ?? null;

        $updateTargets = $this->getKeyTargets($keys, false, $strict);
        $existingUpdater = $this->_getUpdater($updateTargets);

        if ($idKey === null) {
            $closure = static function (
                array $array,
                ?string $service,
                IContainer $container,
                ?ISyncProvider $provider,
                ?ISyncContext $context,
                ?DateFormatter $dateFormatter,
                ?ITreeable $parent
            ) use ($constructor, $updater) {
                $obj = $constructor($array, $service, $container);
                $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                return $obj;
            };
        } else {
            /** @var class-string<TClass> */
            $entityType = $this->_Class->Class;
            $closure = static function (
                array $array,
                ?string $service,
                IContainer $container,
                ?ISyncProvider $provider,
                ?ISyncContext $context,
                ?DateFormatter $dateFormatter,
                ?ITreeable $parent
            ) use ($constructor, $updater, $existingUpdater, $idKey, $entityType) {
                $id = $array[$idKey];
                if ($id === null || !$provider) {
                    $obj = $constructor($array, $service, $container);
                    $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                    return $obj;
                }
                $store = $provider->store();
                $providerId = $provider->getProviderId();
                $obj = $store->getEntity($providerId, $service ?? $entityType, $id, $context);
                if ($obj) {
                    $obj = $existingUpdater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                    return $obj;
                }
                $obj = $constructor($array, $service, $container);
                $obj = $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
                $store->entity($providerId, $service ?? $entityType, $id, $obj);
                return $obj;
            };
        }

        $this->_Class->CreateFromSignatureSyncClosures[$sig] = $closure;
        return $closure;
    }

    /**
     * @template T of ISyncEntity
     * @param SyncOperation::* $operation
     * @param SyncIntrospector<T,SyncIntrospectionClass<T>> $entity
     */
    private function getSyncOperationMethod(int $operation, SyncIntrospector $entity): ?string
    {
        $_entity = $entity->_Class;
        $noun = strtolower($_entity->EntityNoun);
        $plural = strtolower($_entity->EntityPlural);
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
            throw new LogicException('Too many implementations: ' . implode(', ', $methods));
        }

        return reset($methods) ?: null;
    }
}
