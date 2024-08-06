<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\DateFormatterInterface;
use Salient\Contract\Core\Extensible;
use Salient\Contract\Core\HasName;
use Salient\Contract\Core\Normalisable;
use Salient\Contract\Core\NormaliserFactory;
use Salient\Contract\Core\NormaliserFlag;
use Salient\Contract\Core\Providable;
use Salient\Contract\Core\ProviderContextInterface;
use Salient\Contract\Core\ProviderInterface;
use Salient\Contract\Core\Relatable;
use Salient\Contract\Core\SerializeRulesInterface;
use Salient\Contract\Core\Treeable;
use Salient\Utility\Arr;
use Salient\Utility\Get;
use Closure;
use LogicException;
use UnexpectedValueException;

/**
 * Generates closures that perform operations on a class
 *
 * @property-read class-string<TClass> $Class The name of the class under introspection
 * @property-read bool $IsReadable True if the class implements Readable
 * @property-read bool $IsWritable True if the class implements Writable
 * @property-read bool $IsExtensible True if the class implements Extensible
 * @property-read bool $IsProvidable True if the class implements Providable
 * @property-read bool $IsRelatable True if the class implements Relatable
 * @property-read bool $IsTreeable True if the class implements Treeable
 * @property-read bool $HasDates True if the class implements Temporal
 * @property-read array<string,string> $Properties Properties (normalised name => declared name)
 * @property-read array<string,string> $PublicProperties Public properties (normalised name => declared name)
 * @property-read array<string,string> $ReadableProperties Readable properties (normalised name => declared name)
 * @property-read array<string,string> $WritableProperties Writable properties (normalised name => declared name)
 * @property-read array<string,array<string,string>> $Actions Action => normalised property name => "magic" property method
 * @property-read array<string,string> $Parameters Constructor parameters (normalised name => declared name)
 * @property-read array<string,string> $RequiredParameters Parameters that aren't nullable and don't have a default value (normalised name => declared name)
 * @property-read array<string,string> $NotNullableParameters Parameters that aren't nullable and have a default value (normalised name => declared name)
 * @property-read array<string,string> $ServiceParameters Required parameters with a declared type that can be resolved by a service container (normalised name => class/interface name)
 * @property-read array<string,string> $PassByRefParameters Parameters to pass by reference (normalised name => declared name)
 * @property-read array<string,string> $DateParameters Parameters with a declared type that implements DateTimeInterface (normalised name => declared name)
 * @property-read mixed[] $DefaultArguments Default values for (all) constructor parameters
 * @property-read int $RequiredArguments Minimum number of arguments required by the constructor
 * @property-read array<string,int> $ParameterIndex Constructor parameter name => index
 * @property-read string[] $SerializableProperties Declared and "magic" properties that are both readable and writable
 * @property-read string[] $NormalisedKeys Normalised properties (declared and "magic" property names)
 * @property-read string|null $ParentProperty The normalised parent property
 * @property-read string|null $ChildrenProperty The normalised children property
 * @property-read array<string,class-string<Relatable>> $OneToOneRelationships One-to-one relationships between the class and others (normalised property name => target class)
 * @property-read array<string,class-string<Relatable>> $OneToManyRelationships One-to-many relationships between the class and others (normalised property name => target class)
 * @property-read string[] $DateKeys Normalised date properties (declared and "magic" property names)
 *
 * @method string[] getReadableProperties() Get readable properties, including "magic" properties
 * @method string[] getWritableProperties() Get writable properties, including "magic" properties
 * @method bool propertyActionIsAllowed(string $property, IntrospectionClass::ACTION_* $action) True if an action can be performed on a property
 *
 * @template TClass of object
 * @template TProvider of ProviderInterface
 * @template TEntity of Providable
 * @template TContext of ProviderContextInterface
 */
class Introspector
{
    /** @var IntrospectionClass<TClass> */
    protected $_Class;
    /** @var class-string|null */
    protected $_Service;
    /** @var class-string<TProvider> */
    protected $_Provider;
    /** @var class-string<TEntity> */
    protected $_Entity;
    /** @var class-string<TContext> */
    protected $_Context;
    /** @var array<class-string,IntrospectionClass<object>> */
    private static $_IntrospectionClasses = [];

    /**
     * Get an introspector for a service
     *
     * Uses a container to resolve a service to a concrete class and returns an
     * introspector for it.
     *
     * @template T of object
     *
     * @param class-string<T> $service
     * @return static<T,AbstractProvider,AbstractEntity,ProviderContext<AbstractProvider,AbstractEntity>>
     */
    public static function getService(ContainerInterface $container, string $service)
    {
        return new static(
            $service,
            $container->getName($service),
            AbstractProvider::class,
            AbstractEntity::class,
            ProviderContext::class,
        );
    }

    /**
     * Get an introspector for a class
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @return static<T,AbstractProvider,AbstractEntity,ProviderContext<AbstractProvider,AbstractEntity>>
     */
    public static function get(string $class)
    {
        return new static(
            $class,
            $class,
            AbstractProvider::class,
            AbstractEntity::class,
            ProviderContext::class,
        );
    }

    /**
     * Creates a new Introspector object
     *
     * @param class-string $service
     * @param class-string<TClass> $class
     * @param class-string<TProvider> $provider
     * @param class-string<TEntity> $entity
     * @param class-string<TContext> $context
     */
    final protected function __construct(
        string $service,
        string $class,
        string $provider,
        string $entity,
        string $context
    ) {
        $this->_Class =
            self::$_IntrospectionClasses[static::class][$class]
                ?? (self::$_IntrospectionClasses[static::class][$class] = $this->getIntrospectionClass($class));
        $this->_Service = $service === $class ? null : $service;
        $this->_Provider = $provider;
        $this->_Entity = $entity;
        $this->_Context = $context;
    }

    /**
     * @param class-string<TClass> $class
     * @return IntrospectionClass<TClass>
     */
    protected function getIntrospectionClass(string $class): IntrospectionClass
    {
        return new IntrospectionClass($class);
    }

    /**
     * @param mixed[] $arguments
     * @return mixed
     */
    final public function __call(string $name, array $arguments)
    {
        return $this->_Class->{$name}(...$arguments);
    }

    /**
     * @return mixed
     */
    final public function __get(string $name)
    {
        return $this->_Class->{$name};
    }

    /**
     * Normalise strings if the class has a normaliser, otherwise return them
     * as-is
     *
     * @template T of string[]|string
     *
     * @param T $value
     * @param int-mask-of<NormaliserFlag::*> $flags
     * @return T
     *
     * @see Normalisable::normalise()
     * @see NormaliserFactory::getNormaliser()
     */
    final public function maybeNormalise($value, int $flags = NormaliserFlag::GREEDY)
    {
        return $this->_Class->maybeNormalise($value, $flags);
    }

    /**
     * True if the class has a normaliser
     */
    final public function hasNormaliser(): bool
    {
        return $this->_Class->Normaliser !== null;
    }

    /**
     * Get a closure that creates instances of the class from arrays
     *
     * Wraps {@see Introspector::getCreateFromSignatureClosure()} in a closure
     * that resolves array signatures to closures on-demand.
     *
     * @param bool $strict If `true`, the closure will throw an exception if it
     * receives any data that would be discarded.
     * @return Closure(mixed[], ContainerInterface, DateFormatterInterface|null=, Treeable|null=): TClass
     */
    final public function getCreateFromClosure(bool $strict = false): Closure
    {
        $closure =
            $this->_Class->CreateProviderlessFromClosures[(int) $strict]
                ?? null;

        if ($closure) {
            return $closure;
        }

        $closure =
            function (
                array $array,
                ContainerInterface $container,
                ?DateFormatterInterface $dateFormatter = null,
                ?Treeable $parent = null
            ) use ($strict) {
                $keys = array_keys($array);
                $closure = $this->getCreateFromSignatureClosure($keys, $strict);
                return $closure($array, $container, $dateFormatter, $parent);
            };

        $this->_Class->CreateProviderlessFromClosures[(int) $strict] = $closure;

        return $closure;
    }

    /**
     * Get a closure that creates instances of the class from arrays with a
     * given signature
     *
     * @param string[] $keys
     * @param bool $strict If `true`, throw an exception if any data would be
     * discarded.
     * @return Closure(mixed[], ContainerInterface, DateFormatterInterface|null=, Treeable|null=): TClass
     */
    final public function getCreateFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);

        $closure =
            $this->_Class->CreateProviderlessFromSignatureClosures[$sig][(int) $strict]
                ?? null;

        if (!$closure) {
            $closure = $this->_getCreateFromSignatureClosure($keys, $strict);
            $this->_Class->CreateProviderlessFromSignatureClosures[$sig][(int) $strict] = $closure;

            // If the closure was created successfully in strict mode, use it
            // for non-strict purposes too
            if ($strict) {
                $this->_Class->CreateProviderlessFromSignatureClosures[$sig][(int) false] = $closure;
            }
        }

        // Return a closure that injects this introspector's service
        $service = $this->_Service;

        return
            static function (
                array $array,
                ContainerInterface $container,
                ?DateFormatterInterface $dateFormatter = null,
                ?Treeable $parent = null
            ) use ($closure, $service) {
                return $closure(
                    $array,
                    $service,
                    $container,
                    null,
                    null,
                    $dateFormatter,
                    $parent,
                );
            };
    }

    /**
     * Get a closure that creates provider-serviced instances of the class from
     * arrays
     *
     * Wraps {@see Introspector::getCreateProvidableFromSignatureClosure()} in a
     * closure that resolves array signatures to closures on-demand.
     *
     * @param bool $strict If `true`, the closure will throw an exception if it
     * receives any data that would be discarded.
     * @return Closure(mixed[], TProvider, TContext): TClass
     */
    final public function getCreateProvidableFromClosure(bool $strict = false): Closure
    {
        $closure =
            $this->_Class->CreateProvidableFromClosures[(int) $strict]
                ?? null;

        if ($closure) {
            return $closure;
        }

        $closure =
            function (
                array $array,
                ProviderInterface $provider,
                ProviderContextInterface $context
            ) use ($strict) {
                $keys = array_keys($array);
                $closure = $this->getCreateProvidableFromSignatureClosure($keys, $strict);
                return $closure($array, $provider, $context);
            };

        return $this->_Class->CreateProvidableFromClosures[(int) $strict] = $closure;
    }

    /**
     * Get a closure that creates provider-serviced instances of the class from
     * arrays with a given signature
     *
     * @param string[] $keys
     * @param bool $strict If `true`, throw an exception if any data would be
     * discarded.
     * @return Closure(mixed[], TProvider, TContext): TClass
     */
    final public function getCreateProvidableFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);

        $closure =
            $this->_Class->CreateProvidableFromSignatureClosures[$sig][(int) $strict]
                ?? null;

        if (!$closure) {
            $closure = $this->_getCreateFromSignatureClosure($keys, $strict);
            $this->_Class->CreateProvidableFromSignatureClosures[$sig][(int) $strict] = $closure;

            // If the closure was created successfully in strict mode, use it
            // for non-strict purposes too
            if ($strict) {
                $this->_Class->CreateProvidableFromSignatureClosures[$sig][(int) false] = $closure;
            }
        }

        // Return a closure that injects this introspector's service
        $service = $this->_Service;

        return
            static function (
                array $array,
                ProviderInterface $provider,
                ProviderContextInterface $context
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
     * @param string[] $keys
     * @return Closure(mixed[], class-string|null, ContainerInterface, TProvider|null, TContext|null, DateFormatterInterface|null, Treeable|null): TClass
     */
    private function _getCreateFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);
        if ($closure = $this->_Class->CreateFromSignatureClosures[$sig] ?? null) {
            return $closure;
        }

        $targets = $this->getKeyTargets($keys, true, $strict);
        $constructor = $this->_getConstructor($targets);
        $updater = $this->_getUpdater($targets);
        $resolver = $this->_getResolver($targets);

        $closure = static function (
            array $array,
            ?string $service,
            ContainerInterface $container,
            ?ProviderInterface $provider,
            ?ProviderContextInterface $context,
            ?DateFormatterInterface $dateFormatter,
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

        return $this->_Class->CreateFromSignatureClosures[$sig] = $closure;
    }

    /**
     * Get a list of actions required to apply values from an array to a new or
     * existing instance of the class
     *
     * @param string[] $keys
     * @param bool $forNewInstance If `true`, keys are matched with constructor
     * parameters if possible.
     * @param bool $strict If `true`, an exception is thrown if any keys cannot
     * be applied to the class.
     * @param bool $normalised If `true`, the `$keys` array has already been
     * normalised.
     * @param array<static::*_KEY,string> $customKeys An array that maps key
     * types to keys as they appear in `$keys`.
     * @param array<string,Closure(mixed[] $data, string|null $service, TClass $entity, TProvider|null, TContext|null): void> $keyClosures Normalised key => closure
     * @return IntrospectorKeyTargets<static,TClass,TProvider,TContext>
     */
    protected function getKeyTargets(
        array $keys,
        bool $forNewInstance,
        bool $strict,
        bool $normalised = false,
        array $customKeys = [],
        array $keyClosures = []
    ): IntrospectorKeyTargets {
        if (!$normalised) {
            $keys =
                $this->_Class->Normaliser
                    ? array_combine(array_map($this->_Class->CarefulNormaliser, $keys), $keys)
                    : array_combine($keys, $keys);
        }

        /** @var array<string,string> $keys Normalised key => original key */

        // Exclude keys with closures because they can't be passed to the
        // constructor
        $keys = array_diff_key($keys, $keyClosures);

        // Check for missing constructor arguments if preparing an object
        // factory, otherwise check for readonly properties
        if ($forNewInstance) {
            $missing = array_diff_key(
                $this->_Class->RequiredParameters,
                $this->_Class->ServiceParameters,
                $keys,
            );
            if ($missing) {
                throw new LogicException(sprintf(
                    'Cannot call %s::__construct() without: %s',
                    $this->_Class->Class,
                    implode(', ', $missing),
                ));
            }
        } else {
            // Get keys that correspond to constructor parameters and isolate
            // any that don't also match a writable property or "magic" method
            $parameters = array_intersect_key(
                $this->_Class->Parameters,
                $keys,
            );
            $readonly = array_diff_key(
                $parameters,
                array_flip($this->_Class->getWritableProperties()),
            );
            if ($readonly) {
                throw new LogicException(sprintf(
                    'Cannot set readonly properties of %s: %s',
                    $this->_Class->Class,
                    implode(', ', $readonly),
                ));
            }
        }

        // Get keys that correspond to date parameters and properties
        $dateKeys = array_values(array_intersect_key(
            $keys,
            array_flip($this->_Class->DateKeys) + $this->_Class->DateParameters,
        ));

        $keys += $keyClosures;

        // Resolve `$keys` to:
        //
        // - constructor parameters (`$parameterKeys`, `$passByRefKeys`,
        //   `$notNullableKeys`)
        // - callbacks (`$callbackKeys`)
        // - "magic" property methods (`$methodKeys`)
        // - properties (`$propertyKeys`)
        // - arbitrary properties (`$metaKeys`)
        foreach ($keys as $normalisedKey => $key) {
            if ($key instanceof Closure) {
                $callbackKeys[] = $key;
                continue;
            }

            if ($forNewInstance) {
                $param = $this->_Class->Parameters[$normalisedKey] ?? null;
                if ($param !== null) {
                    $parameterKeys[$key] = $this->_Class->ParameterIndex[$param];
                    if (isset($this->_Class->PassByRefParameters[$normalisedKey])) {
                        $passByRefKeys[$key] = true;
                    }
                    if (isset($this->_Class->NotNullableParameters[$normalisedKey])) {
                        $notNullableKeys[$key] = true;
                    }
                    continue;
                }
            }

            $method = $this->_Class->Actions[IntrospectionClass::ACTION_SET][$normalisedKey] ?? null;
            if ($method !== null) {
                $methodKeys[$key] = $method;
                continue;
            }

            $property = $this->_Class->Properties[$normalisedKey] ?? null;
            if ($property !== null) {
                if ($this->_Class->propertyActionIsAllowed(
                    $normalisedKey, IntrospectionClass::ACTION_SET
                )) {
                    $propertyKeys[$key] = $property;
                    continue;
                }
                if ($strict) {
                    throw new LogicException(sprintf(
                        'Cannot set unwritable property: %s::$%s',
                        $this->_Class->Class,
                        $property,
                    ));
                }
                continue;
            }

            if ($this->_Class->IsExtensible) {
                $metaKeys[] = $key;
                continue;
            }

            if ($strict) {
                throw new LogicException(sprintf(
                    'Cannot apply %s to %s',
                    $key,
                    $this->_Class->Class,
                ));
            }
        }

        /** @var IntrospectorKeyTargets<static,TClass,TProvider,TContext> */
        $targets = new IntrospectorKeyTargets(
            $parameterKeys ?? [],
            $passByRefKeys ?? [],
            $notNullableKeys ?? [],
            $callbackKeys ?? [],
            $methodKeys ?? [],
            $propertyKeys ?? [],
            $metaKeys ?? [],
            $dateKeys,
            $customKeys,
        );

        return $targets;
    }

    /**
     * @param IntrospectorKeyTargets<covariant static,TClass,TProvider,TContext> $targets
     * @return Closure(mixed[], class-string|null, ContainerInterface): TClass
     */
    final protected function _getConstructor(IntrospectorKeyTargets $targets): Closure
    {
        $length = max(
            $this->_Class->RequiredArguments,
            $targets->LastParameterIndex + 1,
        );

        $args = array_slice($this->_Class->DefaultArguments, 0, $length);
        $class = $this->_Class->Class;

        if (!$targets->Parameters) {
            return static function (
                array $array,
                ?string $service,
                ContainerInterface $container
            ) use ($args, $class) {
                if ($service && strcasecmp($service, $class)) {
                    /** @var class-string $service */
                    return $container->getAs($class, $service, $args);
                }
                return $container->get($class, $args);
            };
        }

        /** @var array<string,int> Service parameter name => index */
        $serviceArgs = array_intersect_key(
            $this->_Class->ParameterIndex,
            array_flip(array_intersect_key(
                $this->_Class->Parameters,
                $this->_Class->ServiceParameters,
            )),
        );

        // Reduce `$serviceArgs` to arguments in `$args`
        $serviceArgs = array_intersect($serviceArgs, array_keys($args));

        // `null` is never applied to service parameters, so remove unmatched
        // `$args` in service parameter positions and reduce `$serviceArgs` to
        // matched arguments
        $missingServiceArgs = array_diff($serviceArgs, $targets->Parameters);
        $args = array_diff_key($args, array_flip($missingServiceArgs));
        /** @var array<int,string> Service parameter index => `true` */
        $serviceArgs = array_fill_keys(array_intersect(
            $serviceArgs,
            $targets->Parameters,
        ), true);

        $parameterKeys = $targets->Parameters;
        $passByRefKeys = $targets->PassByRefParameters;
        $notNullableKeys = $targets->NotNullableParameters;

        return static function (
            array $array,
            ?string $service,
            ContainerInterface $container
        ) use (
            $args,
            $class,
            $serviceArgs,
            $parameterKeys,
            $passByRefKeys,
            $notNullableKeys
        ) {
            foreach ($parameterKeys as $key => $index) {
                if ($array[$key] === null) {
                    if ($serviceArgs[$index] ?? false) {
                        unset($args[$index]);
                        continue;
                    }
                    if ($notNullableKeys[$key] ?? false) {
                        throw new LogicException(sprintf(
                            "Argument #%d is not nullable, cannot apply value at key '%s': %s::__construct()",
                            $index + 1,
                            $key,
                            $class,
                        ));
                    }
                }
                if ($passByRefKeys[$key] ?? false) {
                    $args[$index] = &$array[$key];
                    continue;
                }
                $args[$index] = $array[$key];
            }

            if ($service && strcasecmp($service, $class)) {
                /** @var class-string $service */
                return $container->getAs($class, $service, $args);
            }

            return $container->get($class, $args);
        };
    }

    /**
     * Get a static closure to perform an action on a property of the class
     *
     * If `$name` and `$action` correspond to a "magic" property method (e.g.
     * `_get<Property>()`), a closure to invoke the method is returned.
     * Otherwise, if `$name` corresponds to an accessible declared property, or
     * the class implements {@see Extensible}), a closure to perform the
     * requested `$action` on the property directly is returned.
     *
     * Fails with an exception if {@see Extensible} is not implemented and no
     * declared or "magic" property matches `$name` and `$action`.
     *
     * Closure signature:
     *
     * ```php
     * static function ($instance, ...$params)
     * ```
     *
     * @param string $action Either {@see IntrospectionClass::ACTION_SET},
     * {@see IntrospectionClass::ACTION_GET},
     * {@see IntrospectionClass::ACTION_ISSET} or
     * {@see IntrospectionClass::ACTION_UNSET}.
     */
    final public function getPropertyActionClosure(string $name, string $action): Closure
    {
        $_name = $this->_Class->maybeNormalise($name, NormaliserFlag::CAREFUL);

        if ($closure = $this->_Class->PropertyActionClosures[$_name][$action] ?? null) {
            return $closure;
        }

        if (!in_array($action, [
            IntrospectionClass::ACTION_SET,
            IntrospectionClass::ACTION_GET,
            IntrospectionClass::ACTION_ISSET,
            IntrospectionClass::ACTION_UNSET
        ])) {
            throw new UnexpectedValueException("Invalid action: $action");
        }

        if ($method = $this->_Class->Actions[$action][$_name] ?? null) {
            $closure = static function ($instance, ...$params) use ($method) {
                return $instance->$method(...$params);
            };
        } elseif ($property = $this->_Class->Properties[$_name] ?? null) {
            if ($this->_Class->propertyActionIsAllowed($_name, $action)) {
                switch ($action) {
                    case IntrospectionClass::ACTION_SET:
                        $closure = static function ($instance, $value) use ($property) { $instance->$property = $value; };
                        break;

                    case IntrospectionClass::ACTION_GET:
                        $closure = static function ($instance) use ($property) { return $instance->$property; };
                        break;

                    case IntrospectionClass::ACTION_ISSET:
                        $closure = static function ($instance) use ($property) { return isset($instance->$property); };
                        break;

                    case IntrospectionClass::ACTION_UNSET:
                        // Removal of a declared property is unlikely to be the
                        // intended outcome, so assign null instead of unsetting
                        $closure = static function ($instance) use ($property) { $instance->$property = null; };
                        break;
                }
            }
        } elseif ($this->_Class->IsExtensible) {
            $method = $action == IntrospectionClass::ACTION_ISSET ? 'isMetaPropertySet' : $action . 'MetaProperty';
            $closure = static function ($instance, ...$params) use ($method, $name) {
                return $instance->$method($name, ...$params);
            };
        }

        if (!$closure) {
            throw new UnexpectedValueException("Unable to perform '$action' on property '$name'");
        }

        $closure = $closure->bindTo(null, $this->_Class->Class);

        return $this->_Class->PropertyActionClosures[$_name][$action] = $closure;
    }

    /**
     * Get a closure that returns the name of an instance on a best-effort basis
     *
     * Intended for use in default {@see HasName::getName()} implementations.
     * Instance names are returned from properties most likely to contain them.
     *
     * @return Closure(TClass): string
     */
    final public function getGetNameClosure(): Closure
    {
        if ($this->_Class->GetNameClosure) {
            return $this->_Class->GetNameClosure;
        }

        $names = [
            'display_name',
            'displayname',
            'name',
            'full_name',
            'fullname',
            'surname',
            'last_name',
            'first_name',
            'title',
            'id',
        ];

        $names = array_combine(
            $names,
            $this->_Class->maybeNormalise($names, NormaliserFlag::CAREFUL)
        );

        $surname = $names['surname'];
        $lastName = $names['last_name'];
        $firstName = $names['first_name'];
        $id = $names['id'];

        $names = array_intersect(
            $names,
            $this->_Class->getReadableProperties()
        );

        // If surname|last_name and first_name exist, use them together,
        // otherwise don't use either of them
        $maybeLast = reset($names);
        if (in_array($maybeLast, [$surname, $lastName], true)) {
            array_shift($names);
            $maybeFirst = reset($names);
            if ($maybeFirst === $firstName) {
                $last = $this->getPropertyActionClosure(
                    $maybeLast,
                    IntrospectionClass::ACTION_GET
                );
                $first = $this->getPropertyActionClosure(
                    $maybeFirst,
                    IntrospectionClass::ACTION_GET
                );

                return $this->_Class->GetNameClosure =
                    static function (
                        $instance
                    ) use ($first, $last): string {
                        return Arr::implode(' ', [
                            $first($instance),
                            $last($instance),
                        ], '');
                    };
            }
        }
        unset($names['last_name']);
        unset($names['first_name']);

        if (!$names) {
            $name = Get::basename($this->_Class->Class);
            $name = "<$name>";
            return $this->_Class->GetNameClosure =
                static function () use ($name): string {
                    return $name;
                };
        }

        $name = array_shift($names);
        $closure = $this->getPropertyActionClosure(
            $name,
            IntrospectionClass::ACTION_GET
        );

        return $this->_Class->GetNameClosure =
            $name === $id
                ? static function ($instance) use ($closure): string {
                    return '#' . $closure($instance);
                }
                : static function ($instance) use ($closure): string {
                    return (string) $closure($instance);
                };
    }

    /**
     * @param SerializeRulesInterface<TClass>|null $rules
     */
    final public function getSerializeClosure(?SerializeRulesInterface $rules = null): Closure
    {
        $rules = $rules
            ? [$rules->getSortByKey(), $this->_Class->IsExtensible && $rules->getIncludeMeta()]
            : [false, $this->_Class->IsExtensible];
        $key = implode("\0", $rules);

        if ($closure = $this->_Class->SerializeClosures[$key] ?? null) {
            return $closure;
        }

        [$sort, $includeMeta] = $rules;
        $methods = $this->_Class->Actions[IntrospectionClass::ACTION_GET] ?? [];
        $props = array_intersect(
            $this->_Class->Properties,
            $this->_Class->ReadableProperties ?: $this->_Class->PublicProperties
        );
        $keys = array_keys($props + $methods);
        if ($sort) {
            sort($keys);
        }

        // Iterators aren't serializable, so they're converted to arrays
        $resolveIterator = function (&$value): void {
            if (is_iterable($value) && !is_array($value)) {
                $value = iterator_to_array($value);
            }
        };
        $closure = (static function ($instance) use ($keys, $methods, $props, $resolveIterator) {
            $arr = [];
            foreach ($keys as $key) {
                if ($method = $methods[$key] ?? null) {
                    $arr[$key] = $instance->{$method}();
                    $resolveIterator($arr[$key]);
                } else {
                    $resolveIterator($instance->{$props[$key]});
                    $arr[$key] = $instance->{$props[$key]};
                }
            }

            return $arr;
        })->bindTo(null, $this->_Class->Class);

        if ($includeMeta) {
            $closure = static function (Extensible $instance) use ($closure) {
                $meta = $instance->getMetaProperties();

                return ($meta ? ['@meta' => $meta] : []) + $closure($instance);
            };
        }

        return $this->_Class->SerializeClosures[$key] = $closure;
    }

    /**
     * @param IntrospectorKeyTargets<covariant static,TClass,TProvider,TContext> $targets
     * @return Closure(mixed[], TClass, ContainerInterface, TProvider|null, TContext|null, DateFormatterInterface|null, Treeable|null): TClass
     */
    final protected function _getUpdater(IntrospectorKeyTargets $targets): Closure
    {
        $isProvidable = $this->_Class->IsProvidable;
        $isTreeable = $this->_Class->IsTreeable;
        $methodKeys = $targets->Methods;
        $propertyKeys = $targets->Properties;
        $metaKeys = $targets->MetaProperties;
        $dateKeys = $targets->DateProperties;

        $closure = static function (
            array $array,
            $obj,
            ContainerInterface $container,
            ?ProviderInterface $provider,
            ?ProviderContextInterface $context,
            ?DateFormatterInterface $dateFormatter,
            ?Treeable $parent
        ) use (
            $isProvidable,
            $isTreeable,
            $methodKeys,
            $propertyKeys,
            $metaKeys,
            $dateKeys
        ) {
            if ($dateKeys) {
                if ($dateFormatter === null) {
                    $dateFormatter =
                        $provider
                            ? $provider->getDateFormatter()
                            : $container->get(DateFormatter::class);
                }

                foreach ($dateKeys as $key) {
                    if (!is_string($array[$key])) {
                        continue;
                    }
                    if ($date = $dateFormatter->parse($array[$key])) {
                        $array[$key] = $date;
                    }
                }
            }

            // The closure is bound to the class for access to protected
            // properties
            if ($propertyKeys) {
                foreach ($propertyKeys as $key => $property) {
                    $obj->$property = $array[$key];
                }
            }

            // Call `setProvider()` and `setContext()` early in case property
            // methods need them
            if ($isProvidable && $provider) {
                if (!$context) {
                    throw new UnexpectedValueException('$context cannot be null when $provider is not null');
                }
                /** @var TClass&TEntity $obj */
                $currentProvider = $obj->getProvider();
                if ($currentProvider === null) {
                    $obj = $obj->setProvider($provider);
                } elseif ($currentProvider !== $provider) {
                    throw new LogicException(sprintf(
                        '%s has wrong provider (%s expected): %s',
                        get_class($obj),
                        $provider->getName(),
                        $currentProvider->getName(),
                    ));
                }
                $obj = $obj->setContext($context);
            }

            // Ditto for `setParent()`
            if ($isTreeable && $parent) {
                /** @var TClass&TEntity&Treeable $obj */
                $obj = $obj->setParent($parent);
            }

            // The closure is bound to the class for access to protected methods
            if ($methodKeys) {
                foreach ($methodKeys as $key => $method) {
                    $obj->$method($array[$key]);
                }
            }

            if ($metaKeys) {
                foreach ($metaKeys as $key) {
                    $obj->setMetaProperty((string) $key, $array[$key]);
                }
            }

            return $obj;
        };

        return $closure->bindTo(null, $this->_Class->Class);
    }

    /**
     * @param IntrospectorKeyTargets<covariant static,TClass,TProvider,TContext> $targets
     * @return Closure(mixed[], string|null, TClass, TProvider|null, TContext|null): TClass
     */
    final protected function _getResolver(IntrospectorKeyTargets $targets): Closure
    {
        $callbackKeys = $targets->Callbacks;

        $closure = static function (
            array $array,
            ?string $service,
            $obj,
            ?ProviderInterface $provider,
            ?ProviderContextInterface $context
        ) use ($callbackKeys) {
            if ($callbackKeys) {
                foreach ($callbackKeys as $callback) {
                    $callback($array, $service, $obj, $provider, $context);
                }
            }

            return $obj;
        };

        return $closure->bindTo(null, $this->_Class->Class);
    }
}
