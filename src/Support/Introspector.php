<?php declare(strict_types=1);

namespace Lkrms\Support;

use Lkrms\Concern\TIntrospector;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IHierarchy;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IProviderContext;
use Lkrms\Contract\ISerializeRules;
use Lkrms\Support\Catalog\NormaliserFlag;
use Lkrms\Support\DateFormatter;
use Lkrms\Utility\Convert;
use Closure;
use LogicException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Use reflection to generate closures that perform operations on a class
 *
 * {@see Introspector} returns values from its underlying
 * {@see IntrospectionClass} instance for any properties declared by
 * `IntrospectionClass` and not declared by `Introspector`.
 *
 * @property-read string $Class The name of the class under introspection
 *
 * @template TClass of object
 * @template TIntrospectionClass of IntrospectionClass
 */
class Introspector
{
    /**
     * @use TIntrospector<TClass,TIntrospectionClass>
     */
    use TIntrospector;

    /**
     * @return mixed
     */
    final public function __get(string $name)
    {
        return $this->_Class->{$name};
    }

    /**
     * @param class-string<TClass> $class
     * @return IntrospectionClass<TClass>
     */
    private function getIntrospectionClass(string $class): IntrospectionClass
    {
        return new IntrospectionClass($class);
    }

    /**
     * Normalise a property name if the class has a normaliser, otherwise return
     * it as-is
     *
     * @template T of string[]|string
     * @param T $value
     * @param int-mask-of<NormaliserFlag::*> $flags
     * @return T
     * @see \Lkrms\Contract\IResolvable::normaliser()
     * @see \Lkrms\Contract\IResolvable::normalise()
     */
    final public function maybeNormalise($value, int $flags = NormaliserFlag::GREEDY)
    {
        return $this->_Class->maybeNormalise($value, $flags);
    }

    /**
     * Return true if the class has a normaliser
     *
     */
    final public function hasNormaliser(): bool
    {
        return !is_null($this->_Class->Normaliser);
    }

    /**
     * Get readable properties, including "magic" properties
     *
     * @return string[] Normalised property names
     */
    final public function getReadableProperties(): array
    {
        return $this->_Class->getReadableProperties();
    }

    /**
     * Get writable properties, including "magic" properties
     *
     * @return string[] Normalised property names
     */
    final public function getWritableProperties(): array
    {
        return $this->_Class->getWritableProperties();
    }

    /**
     * Return true if an action can be performed on a property
     *
     * @param $property The normalised property name to check
     */
    final public function propertyActionIsAllowed(string $property, string $action): bool
    {
        return $this->_Class->propertyActionIsAllowed($property, $action);
    }

    /**
     * Get a closure to create instances of the class from arrays with a given
     * signature
     *
     * @param string[] $keys
     * @param bool $strict If `true`, throw an exception if any data would be
     * discarded.
     * @return Closure(mixed[], IContainer, DateFormatter|null=, IHierarchy|null=): TClass
     */
    final public function getCreateFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);
        $closure = $this->_Class->CreateProviderlessFromSignatureClosures[$sig][(int) $strict] ?? null;
        if (!$closure) {
            $closure = $this->_getCreateFromSignatureClosure($keys, $strict);
            $this->_Class->CreateProviderlessFromSignatureClosures[$sig][(int) $strict] = $closure;

            // If the closure was created successfully in strict mode, cache it
            // for `$strict = false` too
            if ($strict) {
                $this->_Class->CreateProviderlessFromSignatureClosures[$sig][(int) false] = $closure;
            }
        }
        $service = $this->_Service;

        return
            static function (
                array $array,
                IContainer $container,
                ?DateFormatter $dateFormatter = null,
                ?IHierarchy $parent = null
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
     * Get a closure to create instances of the class on behalf of a provider
     * from arrays with a given signature
     *
     * @param string[] $keys
     * @param bool $strict If `true`, throw an exception if any data would be
     * discarded.
     * @return Closure(mixed[], IProvider, IContainer|IProviderContext|null=): TClass
     */
    final public function getCreateProvidableFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\0", $keys);
        $closure = $this->_Class->CreateProvidableFromSignatureClosures[$sig][(int) $strict] ?? null;
        if (!$closure) {
            $closure = $this->_getCreateFromSignatureClosure($keys, $strict);
            $this->_Class->CreateProvidableFromSignatureClosures[$sig][(int) $strict] = $closure;

            // If the closure was created successfully in strict mode, cache it
            // for `$strict = false` purposes too
            if ($strict) {
                $this->_Class->CreateProvidableFromSignatureClosures[$sig][(int) false] = $closure;
            }
        }
        $service = $this->_Service;

        return
            static function (array $array, IProvider $provider, $context = null) use ($closure, $service) {
                [$container, $parent] =
                    $context instanceof IProviderContext
                        ? [$context->container(), $context->getParent()]
                        : [$context ?: $provider->container(), null];

                return $closure(
                    $array,
                    $service,
                    $container,
                    $provider,
                    $context ?: new ProviderContext($container, $parent),
                    $provider->dateFormatter(),
                    $parent,
                );
            };
    }

    /**
     * Get a list of actions required to apply values from an array with a given
     * signature to the properties of a new or existing instance
     *
     * @param string[] $keys
     * @param array<string,string> $customKeys
     * @param array<string,Closure(mixed[], TClass, ?IProvider, ?IProviderContext): void> $keyClosures Normalised key => closure
     * @return IntrospectorKeyTargets<TClass>
     */
    protected function getKeyTargets(
        array $keys,
        bool $withParameters,
        bool $strict,
        bool $normalised = false,
        array $customKeys = [],
        array $keyClosures = []
    ): IntrospectorKeyTargets {
        if (!$normalised) {
            // Normalise array keys (i.e. field/property names)
            $keys = $this->_Class->Normaliser
                ? array_combine(array_map($this->_Class->CarefulNormaliser, $keys), $keys)
                : array_combine($keys, $keys);
        }

        // Remove keys for which a closure is provided, because they can't be
        // resolved before instantiation
        $keys = array_diff_key($keys, $keyClosures);

        // Check for missing constructor parameters if preparing an
        // instantiator, otherwise check for readonly properties
        if ($withParameters) {
            if ($missing = array_diff_key($this->_Class->RequiredParameters, $this->_Class->ServiceParameters, $keys)) {
                throw new UnexpectedValueException("{$this->_Class->Class} constructor requires values for: " . implode(', ', $missing));
            }
        } else {
            // Get keys that correspond to constructor parameters and isolate
            // any that don't also match a writable property or "magic" method
            $parameters = array_intersect_key(
                $this->_Class->Parameters,
                $keys,
                $keyClosures
            );
            $readonly = array_diff(
                array_keys($parameters),
                $this->_Class->getWritableProperties()
            );
            if ($readonly) {
                throw new UnexpectedValueException("Cannot set readonly properties of {$this->_Class->Class}: " . implode(', ', $readonly));
            }
        }

        $keys = array_merge($keys, $keyClosures);

        // Resolve $keys to:
        // - constructor parameters ($parameterKeys, $passByRefKeys)
        // - callbacks ($callbackKeys)
        // - "magic" property methods ($methodKeys)
        // - properties ($propertyKeys)
        // - arbitrary properties ($metaKeys)
        //
        // Keys that correspond to date parameters or properties are also added
        // to $dateKeys
        $parameterKeys = $passByRefKeys = $callbackKeys = $methodKeys = $propertyKeys = $metaKeys = $dateKeys = [];

        foreach ($keys as $normalisedKey => $key) {
            if ($key instanceof Closure) {
                $callbackKeys[] = $key;
                continue;
            }
            if ($withParameters && ($param = $this->_Class->Parameters[$normalisedKey] ?? null)) {
                $parameterKeys[$key] = $this->_Class->ParameterIndex[$param];
                if ($this->_Class->PassByRefParameters[$normalisedKey] ?? null) {
                    $passByRefKeys[$key] = true;
                }
                if ($this->_Class->DateParameters[$normalisedKey] ?? null) {
                    $dateKeys[] = $key;
                    // If found in DateParameters, skip DateKeys check below
                    continue;
                }
            } elseif ($method = $this->_Class->Actions[IntrospectionClass::ACTION_SET][$normalisedKey] ?? null) {
                $methodKeys[$key] = $method;
            } elseif ($property = $this->_Class->Properties[$normalisedKey] ?? null) {
                if (!$this->_Class->propertyActionIsAllowed($normalisedKey, IntrospectionClass::ACTION_SET)) {
                    if ($strict) {
                        throw new UnexpectedValueException("Cannot set unwritable property '{$this->_Class->Class}::$property'");
                    }
                    continue;
                }
                $propertyKeys[$key] = $property;
            } elseif ($this->_Class->IsExtensible) {
                $metaKeys[] = $key;
            } elseif ($strict) {
                throw new UnexpectedValueException("No matching property or constructor parameter found in {$this->_Class->Class} for '$key'");
            } else {
                continue;
            }
            if (in_array($normalisedKey, $this->_Class->DateKeys)) {
                $dateKeys[] = $key;
            }
        }

        /** @var IntrospectorKeyTargets<TClass> */
        $targets = new IntrospectorKeyTargets(
            $parameterKeys,
            $passByRefKeys,
            $callbackKeys,
            $methodKeys,
            $propertyKeys,
            $metaKeys,
            $dateKeys,
            $customKeys,
        );

        return $targets;
    }

    /**
     * @param string[] $keys
     * @return Closure(mixed[], class-string|null, IContainer, IProvider|null, IProviderContext|null, DateFormatter|null, IHierarchy|null): TClass
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

        $closure = static function (
            array $array,
            ?string $service,
            IContainer $container,
            ?IProvider $provider,
            ?IProviderContext $context,
            ?DateFormatter $dateFormatter,
            ?IHierarchy $parent
        ) use ($constructor, $updater) {
            $obj = $constructor($array, $service, $container);
            return $updater($array, $obj, $container, $provider, $context, $dateFormatter, $parent);
        };

        return $this->_Class->CreateFromSignatureClosures[$sig] = $closure;
    }

    /**
     * @param IntrospectorKeyTargets<TClass> $targets
     * @return Closure(mixed[], class-string|null, IContainer): TClass
     */
    protected function _getConstructor(IntrospectorKeyTargets $targets): Closure
    {
        $args = $this->_Class->DefaultArguments;
        $class = $this->_Class->Class;

        if (!$targets->Parameters) {
            return static function (
                array $array,
                ?string $service,
                IContainer $container
            ) use ($args, $class) {
                if ($service && strcasecmp($service, $class)) {
                    /** @var class-string $service */
                    return $container->getAs($class, $service, $args);
                }
                return $container->get($class, $args);
            };
        }

        $parameterKeys = $targets->Parameters;
        $passByRefKeys = $targets->PassByRefParameters;

        return static function (
            array $array,
            ?string $service,
            IContainer $container
        ) use ($args, $class, $parameterKeys, $passByRefKeys) {
            foreach ($parameterKeys as $key => $index) {
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
     * Get a closure to create instances of the class from arrays
     *
     * This method is similar to
     * {@see Introspector::getCreateFromSignatureClosure()}, but it returns a
     * closure that resolves array signatures when called.
     *
     * @param bool $strict If `true`, return a closure that throws an exception
     * if any data would be discarded.
     * @return Closure(mixed[], IContainer, DateFormatter|null=, IHierarchy|null=): TClass
     */
    final public function getCreateFromClosure(bool $strict = false): Closure
    {
        if ($closure = $this->_Class->CreateProviderlessFromClosures[(int) $strict] ?? null) {
            return $closure;
        }

        $closure =
            function (
                array $array,
                IContainer $container,
                ?DateFormatter $dateFormatter = null,
                ?IHierarchy $parent = null
            ) use ($strict) {
                $keys = array_keys($array);

                return ($this->getCreateFromSignatureClosure($keys, $strict))($array, $container, $dateFormatter, $parent);
            };

        return $this->_Class->CreateProviderlessFromClosures[(int) $strict] = $closure;
    }

    /**
     * Get a closure to create instances of the class from arrays on behalf of a
     * provider
     *
     * This method is similar to
     * {@see Introspector::getCreateProvidableFromSignatureClosure()}, but it
     * returns a closure that resolves array signatures when called.
     *
     * @param bool $strict If `true`, return a closure that throws an exception
     * if any data would be discarded.
     * @return Closure(mixed[], IProvider, IContainer|IProviderContext|null=)
     * ```php
     * function (array $array, IProvider $provider, IContainer|IProviderContext|null $context = null)
     * ```
     */
    final public function getCreateProvidableFromClosure(bool $strict = false): Closure
    {
        if ($closure = $this->_Class->CreateProvidableFromClosures[(int) $strict] ?? null) {
            return $closure;
        }

        $closure =
            function (array $array, IProvider $provider, $context = null) use ($strict) {
                $keys = array_keys($array);

                return ($this->getCreateProvidableFromSignatureClosure($keys, $strict))($array, $provider, $context);
            };

        return $this->_Class->CreateProvidableFromClosures[(int) $strict] = $closure;
    }

    /**
     * Get a static closure to perform an action on a property of the class
     *
     * If `$name` and `$action` correspond to a "magic" property method (e.g.
     * `_get<Property>()`), a closure to invoke the method is returned.
     * Otherwise, if `$name` corresponds to an accessible declared property, or
     * the class implements {@see IExtensible}), a closure to perform the
     * requested `$action` on the property directly is returned.
     *
     * Fails with an exception if {@see IExtensible} is not implemented and no
     * declared or "magic" property matches `$name` and `$action`.
     *
     * Closure signature:
     *
     * ```php
     * static function ($instance, ...$params)
     * ```
     *
     * @param string $name
     * @param string $action Either {@see IntrospectionClass::ACTION_SET},
     * {@see IntrospectionClass::ACTION_GET},
     * {@see IntrospectionClass::ACTION_ISSET} or
     * {@see IntrospectionClass::ACTION_UNSET}.
     * @return Closure
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
            throw new RuntimeException("Unable to perform '$action' on property '$name'");
        }

        $closure = $closure->bindTo(null, $this->_Class->Class);

        return $this->_Class->PropertyActionClosures[$_name][$action] = $closure;
    }

    final public function getGetNameClosure(): Closure
    {
        if ($this->_Class->GetNameClosure) {
            return $this->_Class->GetNameClosure;
        }

        $names = $this->_Class->maybeNormalise([
            'display_name',
            'displayname',
            'name',
            'full_name',
            'fullname',
            'surname',
            'last_name',
            'first_name',
            'title',
            'description',
            'id',
        ], NormaliserFlag::CAREFUL);

        $names = array_intersect($names, $this->_Class->getReadableProperties());

        // If surname|last_name and first_name exist, use them together,
        // otherwise don't use either of them
        if (in_array($last = reset($names), ['surname', 'last_name'])) {
            array_shift($names);
            if (($first = reset($names)) == 'first_name') {
                $last = $this->getPropertyActionClosure($last, IntrospectionClass::ACTION_GET);
                $first = $this->getPropertyActionClosure($first, IntrospectionClass::ACTION_GET);

                return $this->_Class->GetNameClosure =
                    static function ($instance) use ($first, $last): ?string {
                        return Convert::sparseToString(' ', [$first($instance), $last($instance)]) ?: null;
                    };
            }
        }
        while (in_array(reset($names), ['last_name', 'first_name'])) {
            array_shift($names);
        }

        if (!$names) {
            return $this->_Class->GetNameClosure = static function (): ?string { return null; };
        }

        return $this->_Class->GetNameClosure = $this->getPropertyActionClosure(
            array_shift($names),
            IntrospectionClass::ACTION_GET
        );
    }

    final public function getSerializeClosure(?ISerializeRules $rules = null): Closure
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
            $closure = static function (IExtensible $instance) use ($closure) {
                $meta = $instance->getMetaProperties();

                return ($meta ? ['@meta' => $meta] : []) + $closure($instance);
            };
        }

        return $this->_Class->SerializeClosures[$key] = $closure;
    }

    /**
     * @param IntrospectorKeyTargets<TClass> $targets
     * @return Closure(mixed[], TClass, IContainer, IProvider|null, IProviderContext|null, DateFormatter|null, IHierarchy|null): TClass
     */
    protected function _getUpdater(IntrospectorKeyTargets $targets): Closure
    {
        $isProvidable = $this->_Class->IsProvidable;
        $isHierarchy = $this->_Class->IsHierarchy;
        $callbackKeys = $targets->Callbacks;
        $methodKeys = $targets->Methods;
        $propertyKeys = $targets->Properties;
        $metaKeys = $targets->MetaProperties;
        $dateKeys = $targets->DateProperties;

        $closure = static function (
            array $array,
            $obj,
            IContainer $container,
            ?IProvider $provider,
            ?IProviderContext $context,
            ?DateFormatter $dateFormatter,
            ?IHierarchy $parent
        ) use (
            $isProvidable,
            $isHierarchy,
            $callbackKeys,
            $methodKeys,
            $propertyKeys,
            $metaKeys,
            $dateKeys
        ) {
            if ($dateKeys) {
                if ($dateFormatter === null) {
                    $dateFormatter =
                        $provider
                            ? $provider->dateFormatter()
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
                /** @var IProvidable<IProvider,IProviderContext> $obj */
                $currentProvider = $obj->provider();
                if ($currentProvider === null) {
                    $obj = $obj->setProvider($provider);
                } elseif ($currentProvider !== $provider) {
                    throw new LogicException(sprintf(
                        '%s has wrong provider (%s expected): %s',
                        get_class($obj),
                        $provider->name(),
                        $currentProvider->name(),
                    ));
                }
                $obj = $obj->setContext($context);
            }

            // Ditto for `setParent()`
            if ($isHierarchy && $parent) {
                /** @var IHierarchy $obj */
                $obj = $obj->setParent($parent);
            }

            // The closure is bound to the class for access to protected methods
            if ($methodKeys) {
                foreach ($methodKeys as $key => $method) {
                    $obj->$method($array[$key]);
                }
            }

            if ($callbackKeys) {
                foreach ($callbackKeys as $callback) {
                    $callback($array, $obj, $provider, $context);
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
}
