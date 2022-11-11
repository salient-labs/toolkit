<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use DateTimeInterface;
use Lkrms\Container\Container;
use Lkrms\Contract\HasDateProperties;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IHierarchy;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvidableContext;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IResolvable;
use Lkrms\Contract\ISerializeRules;
use Lkrms\Contract\IWritable;
use Lkrms\Facade\Reflect;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use RuntimeException;
use UnexpectedValueException;

class ClosureBuilder
{
    public const ACTION_GET   = "get";
    public const ACTION_ISSET = "isset";
    public const ACTION_SET   = "set";
    public const ACTION_UNSET = "unset";

    /**
     * @var string
     */
    private $Class;

    /**
     * @var string|null
     */
    private $Service;

    /**
     * @var bool
     */
    private $IsReadable;

    /**
     * @var bool
     */
    private $IsWritable;

    /**
     * @var bool
     */
    private $IsExtensible;

    /**
     * @var bool
     */
    private $IsProvidable;

    /**
     * @var bool
     */
    private $IsHierarchy;

    /**
     * @var bool
     */
    private $HasDates;

    /**
     * Property names
     *
     * - `public` properties
     * - `protected` properties if the class implements {@see IReadable} or
     *   {@see IWritable}
     *
     * @var string[]
     */
    private $Properties = [];

    /**
     * Public property names
     *
     * @var string[]
     */
    private $PublicProperties = [];

    /**
     * Readable property names
     *
     * Empty if the class does not implement {@see IReadable}, otherwise:
     * - `public` properties
     * - `protected` properties returned by {@see IReadable::getReadable()}
     *
     * Does not include "magic" property names.
     *
     * @var string[]
     */
    private $ReadableProperties = [];

    /**
     * Writable property names
     *
     * Empty if the class does not implement {@see IWritable}, otherwise:
     * - `public` properties
     * - `protected` properties returned by {@see IWritable::getWritable()}
     *
     * Does not include "magic" property names.
     *
     * @var string[]
     */
    private $WritableProperties = [];

    /**
     * Normalised date property names
     *
     * Includes "magic" property names.
     *
     * @var string[]
     */
    private $DateProperties = [];

    /**
     * "Magic" property names => supported actions => method names
     *
     * @var array<string,array<string,string>>
     */
    private $Methods = [];

    /**
     * Actions => normalised property names => "magic" method names
     *
     * @var array<string,array<string,string>>
     */
    private $Actions = [];

    /**
     * Constructor parameter names, in order of appearance
     *
     * @var string[]
     */
    private $Parameters = [];

    /**
     * Parameters that aren't nullable and don't have a default value
     *
     * @var string[]
     */
    private $RequiredParameters = [];

    /**
     * Default constructor arguments
     *
     * @var array
     */
    private $DefaultArguments = [];

    /**
     * Normalised property names => declared property names
     *
     * @var array<string,string>
     */
    private $PropertyMap = [];

    /**
     * Normalised property names => "magic" property names
     *
     * @var array<string,string>
     */
    private $MethodMap = [];

    /**
     * Normalised constructor parameter names => constructor parameter names
     *
     * @var array<string,string>
     */
    private $ParameterMap = [];

    /**
     * Normalised constructor parameter names => constructor parameter names
     *
     * @var array<string,string>
     */
    private $RequiredMap = [];

    /**
     * Normalised constructor parameter names => constructor parameter names
     *
     * @var array<string,string>
     */
    private $DateParameters = [];

    /**
     * Normalised constructor parameter names => class names
     *
     * @var array<string,string>
     */
    private $ServiceMap = [];

    /**
     * Normalised property names, whether declared or "magic"
     *
     * @var string[]
     */
    private $NormalisedProperties = [];

    /**
     * Constructor parameter names => constructor argument indices
     *
     * @var array<string,int>
     */
    private $ParametersIndex = [];

    /**
     * Converts property names to normalised property names
     *
     * @var callable|null
     */
    private $Normaliser;

    /**
     * Normalises property names with $aggressive = false
     *
     * @var callable|null
     */
    private $GentleNormaliser;

    /**
     * Normalises property names with $hints = $this->NormalisedProperties
     *
     * @var callable|null
     */
    private $CarefulNormaliser;

    /**
     * @var array<string,array<string,Closure>>
     */
    private $PropertyActionClosures = [];

    /**
     * @var array<int,Closure>
     */
    private $CreateProviderlessFromClosures = [];

    /**
     * @var array<int,Closure>
     */
    private $CreateProvidableFromClosures = [];

    /**
     * @var array<string,Closure>
     */
    private $CreateFromSignatureClosures = [];

    /**
     * @var array<string,array<int,Closure>>
     */
    private $CreateProviderlessFromSignatureClosures = [];

    /**
     * @var array<string,array<int,Closure>>
     */
    private $CreateProvidableFromSignatureClosures = [];

    /**
     * @var array<string,Closure>
     */
    private $SerializeClosures = [];

    /**
     * @var array<string,array<string,static>>
     */
    private static $Instances = [];

    /**
     * Get a ClosureBuilder for $class after optionally using a container to
     * resolve it to a concrete class
     *
     * @return static
     */
    final public static function maybeGetBound(?IContainer $container, string $class)
    {
        return is_null($container)
            ? static::get($class)
            : static::getBound($container, $class);
    }

    /**
     * Get a ClosureBuilder for $class after using a container to resolve it to
     * a concrete class
     *
     * @return static
     */
    final public static function getBound(IContainer $container, string $class)
    {
        $instance = static::get($container->getName($class));
        $instance->Service = $class;

        return $instance;
    }

    /**
     * Get a ClosureBuilder for $class
     *
     * @return static
     */
    final public static function get(string $class)
    {
        return self::$Instances[static::class][$class]
            ?? (self::$Instances[static::class][$class] = new static($class));
    }

    final private function __construct(string $class)
    {
        $class = new ReflectionClass($class);
        $this->load($class);
    }

    protected function load(ReflectionClass $class): void
    {
        $this->Class        = $class->name;
        $this->IsReadable   = $class->implementsInterface(IReadable::class);
        $this->IsWritable   = $class->implementsInterface(IWritable::class);
        $this->IsExtensible = $class->implementsInterface(IExtensible::class);
        $this->IsProvidable = $class->implementsInterface(IProvidable::class);
        $this->IsHierarchy  = $class->implementsInterface(IHierarchy::class);
        $this->HasDates     = $class->implementsInterface(HasDateProperties::class);

        // IResolvable provides access to properties via alternative names
        if ($class->implementsInterface(IResolvable::class))
        {
            $this->Normaliser        = $class->getMethod("getNormaliser")->invoke(null);
            $this->GentleNormaliser  = fn(string $name): string => ($this->Normaliser)($name, false);
            $this->CarefulNormaliser = fn(string $name): string => ($this->Normaliser)($name, true, ...$this->NormalisedProperties);
        }

        $propertyFilter = ReflectionProperty::IS_PUBLIC;
        $methodFilter   = 0;

        // IReadable and IWritable provide access to protected and "magic"
        // property methods
        if ($this->IsReadable || $this->IsWritable)
        {
            $propertyFilter |= ReflectionProperty::IS_PROTECTED;
            $methodFilter   |= ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
        }

        // Get instance properties
        $properties = array_values(array_filter(
            $class->getProperties($propertyFilter),
            fn(ReflectionProperty $prop) => !$prop->isStatic()
        ));
        $this->Properties = Reflect::getNames($properties);

        if ($propertyFilter & ReflectionProperty::IS_PROTECTED)
        {
            $this->PublicProperties = Reflect::getNames(
                array_values(array_filter(
                    $properties,
                    fn(ReflectionProperty $prop) => $prop->isPublic()
                ))
            );
        }
        else
        {
            $this->PublicProperties = $this->Properties;
        }

        if ($this->IsReadable)
        {
            $readable = $class->getMethod("getReadable")->invoke(null);
            $readable = array_merge(
                ["*"] === $readable ? $this->Properties : $readable,
                $this->PublicProperties
            );
            $this->ReadableProperties = array_intersect($this->Properties, $readable);
        }

        if ($this->IsWritable)
        {
            $writable = $class->getMethod("getWritable")->invoke(null);
            $writable = array_merge(
                ["*"] === $writable ? $this->Properties : $writable,
                $this->PublicProperties
            );
            $this->WritableProperties = array_intersect($this->Properties, $writable);
        }

        // Get "magic" property methods, e.g. _get<Property>()
        if ($methodFilter)
        {
            $actions = [];
            if ($this->IsReadable)
            {
                $actions[] = self::ACTION_GET;
                $actions[] = self::ACTION_ISSET;
            }
            if ($this->IsWritable)
            {
                $actions[] = self::ACTION_SET;
                $actions[] = self::ACTION_UNSET;
            }
            $regex = '/^_(?P<action>' . implode("|", $actions) . ')(?P<property>.+)$/i';

            foreach ($class->getMethods($methodFilter) as $method)
            {
                if (!$method->isStatic() && preg_match($regex, $method->name, $match))
                {
                    [$action, $property] = [strtolower($match["action"]), $match["property"]];
                    $this->Methods[$property][$action] = $method->name;
                    $this->Actions[$action][$this->maybeNormalise($property, true)] = $method->name;
                }
            }
        }

        // Get constructor parameters
        if (($constructor = $class->getConstructor()) && $constructor->isPublic())
        {
            foreach ($constructor->getParameters() as $param)
            {
                $normalised   = $this->maybeNormalise($param->name, true);
                $defaultValue = null;
                if ($param->isOptional())
                {
                    $defaultValue = $param->getDefaultValue();
                }
                elseif (!$param->allowsNull())
                {
                    $this->RequiredParameters[] = $this->RequiredMap[$normalised] = $param->name;
                    if (($type = $param->getType()) &&
                        $type instanceof ReflectionNamedType && !$type->isBuiltin())
                    {
                        $this->ServiceMap[$normalised] = $type = $type->getName();
                        if ($this->HasDates && is_a($type, DateTimeInterface::class, true))
                        {
                            $this->DateParameters[] = $normalised;
                        }
                    }
                }
                $this->Parameters[]       = $this->ParameterMap[$normalised] = $param->name;
                $this->DefaultArguments[] = $defaultValue;
            }
            $this->ParametersIndex = array_flip($this->Parameters);
        }

        // Create normalised property name maps
        $methodProperties = array_keys($this->Methods);
        if ($this->Normaliser)
        {
            $this->PropertyMap = array_combine(
                array_map($this->GentleNormaliser, $this->Properties),
                $this->Properties
            );
            $this->MethodMap = array_combine(
                array_map($this->GentleNormaliser, $methodProperties),
                $methodProperties
            );
        }
        else
        {
            $this->PropertyMap = array_combine($this->Properties, $this->Properties);
            $this->MethodMap   = array_combine($methodProperties, $methodProperties);
        }

        // And a list of unique normalised property names
        $this->NormalisedProperties = array_keys($this->PropertyMap + $this->MethodMap);

        if ($this->HasDates)
        {
            $dates = $class->getMethod("getDateProperties")->invoke(null);
            $this->DateProperties = ["*"] === $dates
                ? $this->NormalisedProperties
                : array_intersect($this->NormalisedProperties, $this->maybeNormalise($dates, true));
        }
    }

    /**
     * @param string|string[] $value
     * @return string|string[]
     */
    final public function maybeNormalise($value, bool $gentle = false, bool $careful = false)
    {
        if (!$this->Normaliser)
        {
            return $value;
        }

        $normaliser = ($gentle
            ? $this->GentleNormaliser
            : ($careful ? $this->CarefulNormaliser : $this->Normaliser));

        if (is_array($value))
        {
            return array_map($normaliser, $value);
        }

        return ($normaliser)($value);
    }

    final public function hasNormaliser(): bool
    {
        return !is_null($this->Normaliser);
    }

    /**
     * @return string[]
     */
    final public function getReadableProperties(): array
    {
        return $this->ReadableProperties ?: $this->PublicProperties;
    }

    /**
     * @return string[]
     */
    final public function getWritableProperties(): array
    {
        return $this->WritableProperties ?: $this->PublicProperties;
    }

    /**
     * @param bool $strict If `true`, throw an exception if the closure would
     * discard unusable data.
     * @return Closure
     * ```php
     * closure(array $array, \Lkrms\Contract\IContainer $container, ?\Lkrms\Contract\IHierarchy $parent = null, ?\Lkrms\Support\DateFormatter $dateFormatter = null)
     * ```
     */
    final public function getCreateFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\000", $keys);

        if ($closure = $this->CreateProviderlessFromSignatureClosures[$sig][(int)$strict] ?? null)
        {
            return $closure;
        }

        $closure = $this->_getCreateFromSignatureClosure($keys, $strict);
        $closure = static function (array $array, IContainer $container, ?IHierarchy $parent = null, ?DateFormatter $dateFormatter = null) use ($closure)
        {
            return $closure($container, $array, null, null, $parent, $dateFormatter);
        };

        $this->CreateProviderlessFromSignatureClosures[$sig][(int)$strict] = $closure;
        // If the closure was created successfully in strict mode, cache it for
        // `$strict = false` purposes too
        if ($strict && !($this->CreateProviderlessFromSignatureClosures[$sig][(int)false] ?? null))
        {
            $this->CreateProviderlessFromSignatureClosures[$sig][(int)false] = $closure;
        }

        return $closure;
    }

    /**
     * @param bool $strict If `true`, throw an exception if the closure would
     * discard unusable data.
     * @return Closure
     * ```php
     * closure(array $array, \Lkrms\Contract\IProvider $provider, \Lkrms\Contract\IContainer|\Lkrms\Contract\IProvidableContext|null $context = null)
     * ```
     */
    final public function getCreateProvidableFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\000", $keys);

        if ($closure = $this->CreateProvidableFromSignatureClosures[$sig][(int)$strict] ?? null)
        {
            return $closure;
        }

        $closure = $this->_getCreateFromSignatureClosure($keys, $strict);
        $closure = static function (array $array, IProvider $provider, $context = null) use ($closure)
        {
            [$container, $parent] = ($context instanceof IProvidableContext
                ? [$context->container(), $context->getParent()]
                : [$context ?: $provider->container(), null]);

            return $closure($container, $array, $provider,
                $context ?: new ProvidableContext($container, $parent),
                $parent, $provider->getDateFormatter());
        };

        $this->CreateProvidableFromSignatureClosures[$sig][(int)$strict] = $closure;
        // If the closure was created successfully in strict mode, cache it for
        // `$strict = false` purposes too
        if ($strict && !($this->CreateProvidableFromSignatureClosures[$sig][(int)false] ?? null))
        {
            $this->CreateProvidableFromSignatureClosures[$sig][(int)false] = $closure;
        }

        return $closure;
    }

    final protected function getProperties(array $keys, bool $withParameters, bool $strict): ClosureBuilderProperties
    {
        // Normalise array keys (i.e. field/property names)
        if ($this->Normaliser)
        {
            $keys = array_combine(
                array_map($this->CarefulNormaliser, $keys),
                $keys
            );
        }
        else
        {
            $keys = array_combine($keys, $keys);
        }

        // Check for missing constructor parameters if preparing an
        // instantiator, otherwise check for readonly properties
        if ($withParameters)
        {
            if ($missing = array_diff_key($this->RequiredMap, $this->ServiceMap, $keys))
            {
                throw new UnexpectedValueException("{$this->Class} constructor requires values for: " . implode(", ", $missing));
            }
        }
        else
        {
            // Get keys that correspond to constructor parameters and isolate
            // any that don't also match a writable property or "magic" method
            $parameters = array_intersect_key($this->ParameterMap, $keys);
            $writable   = array_intersect($this->PropertyMap, $this->WritableProperties ?: $this->PublicProperties);

            if ($readonly = array_diff_key($parameters, $writable, $this->Actions[self::ACTION_SET] ?? []))
            {
                throw new UnexpectedValueException("Cannot set readonly properties of {$this->Class}: " . implode(", ", $readonly));
            }
        }

        // Resolve $keys to:
        // - constructor parameters ($parameterKeys)
        // - "magic" property methods ($methodKeys)
        // - properties ($propertyKeys)
        // - arbitrary properties ($metaKeys)
        $parameterKeys = $methodKeys = $propertyKeys = $metaKeys = $dateKeys = [];

        foreach ($keys as $normalisedKey => $key)
        {
            if ($withParameters && ($param = $this->ParameterMap[$normalisedKey] ?? null))
            {
                $parameterKeys[$key] = $this->ParametersIndex[$param];
                if (in_array($normalisedKey, $this->DateParameters))
                {
                    $dateKeys[] = $key;
                    continue;
                }
            }
            elseif ($method = $this->Actions[self::ACTION_SET][$normalisedKey] ?? null)
            {
                $methodKeys[$key] = $method;
            }
            elseif ($property = $this->PropertyMap[$normalisedKey] ?? null)
            {
                if (!$this->checkWritable($property, self::ACTION_SET))
                {
                    continue;
                }
                $propertyKeys[$key] = $property;
            }
            elseif ($this->IsExtensible)
            {
                $metaKeys[] = $key;
            }
            elseif ($strict)
            {
                throw new UnexpectedValueException("No matching property or constructor parameter found in {$this->Class} for '$key'");
            }
            else
            {
                continue;
            }
            if (in_array($normalisedKey, $this->DateProperties))
            {
                $dateKeys[] = $key;
            }
        }

        return new ClosureBuilderProperties($parameterKeys, $methodKeys, $propertyKeys, $metaKeys, $dateKeys);
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    private function _getCreateFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\000", $keys);

        if ($closure = $this->CreateFromSignatureClosures[$sig] ?? null)
        {
            return $closure;
        }

        $properties = $this->getProperties($keys, true, $strict);
        [$parameterKeys, $propertyKeys, $methodKeys, $metaKeys, $dateKeys] = [
            $properties->Parameters,
            $properties->Properties,
            $properties->Methods,
            $properties->MetaProperties,
            $properties->DateProperties,
        ];

        // Build the smallest possible chain of closures
        $closure = ($parameterKeys
            ? $this->_getConstructor($parameterKeys)
            : $this->_getDefaultConstructor());
        if ($propertyKeys)
        {
            $closure = $this->_getPropertyClosure($propertyKeys, $closure);
        }
        // Call `setProvider()` and `setContext()` early in case property
        // methods need them
        if ($this->IsProvidable)
        {
            $closure = $this->_getProvidableClosure($closure);
        }
        // Ditto for `setParent()`
        if ($this->IsHierarchy)
        {
            $closure = $this->_getHierarchyClosure($closure);
        }
        if ($methodKeys)
        {
            $closure = $this->_getMethodClosure($methodKeys, $closure);
        }
        if ($metaKeys)
        {
            $closure = $this->_getMetaClosure($metaKeys, $closure);
        }
        if ($dateKeys)
        {
            $closure = $this->_getDateClosure($dateKeys, $closure);
        }

        return $this->CreateFromSignatureClosures[$sig] = $closure;
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    protected function _getConstructor(array $parameterKeys): Closure
    {
        [$defaultArgs, $service, $class] = [
            $this->DefaultArguments,
            $this->Service,
            $this->Class,
        ];

        return static function (IContainer $container, array $array) use ($parameterKeys, $defaultArgs, $service, $class)
        {
            $args = $defaultArgs;
            foreach ($parameterKeys as $key => $index)
            {
                $args[$index] = $array[$key];
            }
            if ($service && strcasecmp($service, $class) && $container instanceof Container)
            {
                return $container->getAs($class, $service, ...$args);
            }

            return $container->get($class, ...$args);
        };
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    protected function _getDefaultConstructor(): Closure
    {
        [$defaultArgs, $service, $class] = [
            $this->DefaultArguments,
            $this->Service,
            $this->Class,
        ];

        return static function (IContainer $container) use ($defaultArgs, $service, $class)
        {
            if ($service && strcasecmp($service, $class) && $container instanceof Container)
            {
                return $container->getAs($class, $service, ...$defaultArgs);
            }

            return $container->get($class, ...$defaultArgs);
        };
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    protected function _getPropertyClosure(array $propertyKeys, Closure $closure): Closure
    {
        return static function (IContainer $container, array $array, ...$args) use ($propertyKeys, $closure)
        {
            $obj = $closure($container, $array, ...$args);
            foreach ($propertyKeys as $key => $property)
            {
                $obj->$property = $array[$key];
            }
            return $obj;
        };
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    protected function _getProvidableClosure(Closure $closure): Closure
    {
        return static function (IContainer $container, array $array, ?IProvider $provider, ?IProvidableContext $context, ...$args) use ($closure)
        {
            /** @var IProvidable $obj */
            $obj = $closure($container, $array, $provider, $context, ...$args);
            if ($provider)
            {
                return $obj->setProvider($provider)->setContext($context);
            }

            return $obj;
        };
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    protected function _getHierarchyClosure(Closure $closure): Closure
    {
        return static function (IContainer $container, array $array, ?IProvider $provider, ?IProvidableContext $context, ?IHierarchy $parent, ...$args) use ($closure)
        {
            /** @var IHierarchy $obj */
            $obj = $closure($container, $array, $provider, $context, $parent, ...$args);
            if ($parent)
            {
                return $obj->setParent($parent);
            }

            return $obj;
        };
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    protected function _getMethodClosure(array $methodKeys, Closure $closure): Closure
    {
        return static function (IContainer $container, array $array, ...$args) use ($methodKeys, $closure)
        {
            $obj = $closure($container, $array, ...$args);
            foreach ($methodKeys as $key => $method)
            {
                $obj->$method($array[$key]);
            }

            return $obj;
        };
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    protected function _getMetaClosure(array $metaKeys, Closure $closure): Closure
    {
        return static function (IContainer $container, array $array, ...$args) use ($metaKeys, $closure)
        {
            $obj = $closure($container, $array, ...$args);
            foreach ($metaKeys as $key)
            {
                $obj->setMetaProperty((string)$key, $array[$key]);
            }

            return $obj;
        };
    }

    /**
     * @return Closure
     * ```php
     * closure(\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent, ?\Lkrms\Support\DateFormatter $dateFormatter)
     * ```
     */
    protected function _getDateClosure(array $dateKeys, Closure $closure): Closure
    {
        return static function (IContainer $container, array $array, ?IProvider $provider, ?IProvidableContext $context, ?IHierarchy $parent, ?DateFormatter $dateFormatter, ...$args) use ($dateKeys, $closure)
        {
            if (is_null($dateFormatter))
            {
                /** @var DateFormatter $dateFormatter */
                $dateFormatter = ($provider
                    ? $provider->getDateFormatter()
                    : $container->get(DateFormatter::class));
            }

            foreach ($dateKeys as $key)
            {
                if (!is_string($array[$key]))
                {
                    continue;
                }
                if ($date = $dateFormatter->parse($array[$key]))
                {
                    $array[$key] = $date;
                }
            }

            return $closure($container, $array, $provider, $context, $parent, $dateFormatter, ...$args);
        };
    }

    /**
     * @param bool $strict If `true`, return a closure that throws an exception
     * if `$array` contains unusable values.
     * @return Closure
     * ```php
     * closure(array $array, \Lkrms\Contract\IContainer $container, ?\Lkrms\Contract\IHierarchy $parent = null, ?\Lkrms\Support\DateFormatter $dateFormatter = null)
     * ```
     */
    final public function getCreateFromClosure(bool $strict = false): Closure
    {
        if ($closure = $this->CreateProviderlessFromClosures[(int)$strict] ?? null)
        {
            return $closure;
        }

        $closure = function (array $array, IContainer $container, ?IHierarchy $parent = null, ?DateFormatter $dateFormatter = null) use ($strict)
        {
            $keys = array_keys($array);
            return ($this->getCreateFromSignatureClosure($keys, $strict))($array, $container, $parent, $dateFormatter);
        };

        return $this->CreateProviderlessFromClosures[(int)$strict] = $closure;
    }

    /**
     * @param bool $strict If `true`, return a closure that throws an exception
     * if `$array` contains unusable values.
     * @return Closure
     * ```php
     * closure(array $array, \Lkrms\Contract\IProvider $provider, \Lkrms\Contract\IContainer|\Lkrms\Contract\IProvidableContext|null $context = null)
     * ```
     */
    final public function getCreateProvidableFromClosure(bool $strict = false): Closure
    {
        if ($closure = $this->CreateProvidableFromClosures[(int)$strict] ?? null)
        {
            return $closure;
        }

        $closure = function (array $array, IProvider $provider, $context = null) use ($strict)
        {
            $keys = array_keys($array);
            return ($this->getCreateProvidableFromSignatureClosure($keys, $strict))($array, $provider, $context);
        };

        return $this->CreateProvidableFromClosures[(int)$strict] = $closure;
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
     * @param string $action Either {@see ClosureBuilder::ACTION_SET},
     * {@see ClosureBuilder::ACTION_GET}, {@see ClosureBuilder::ACTION_ISSET} or
     * {@see ClosureBuilder::ACTION_UNSET}.
     * @return Closure
     */
    final public function getPropertyActionClosure(string $name, string $action): Closure
    {
        $_name = $this->maybeNormalise($name, false, true);

        if ($closure = $this->PropertyActionClosures[$_name][$action] ?? null)
        {
            return $closure;
        }

        if (!in_array($action, [
            self::ACTION_SET,
            self::ACTION_GET,
            self::ACTION_ISSET,
            self::ACTION_UNSET
        ]))
        {
            throw new UnexpectedValueException("Invalid action: $action");
        }

        if ($method = $this->Methods[$this->MethodMap[$_name] ?? $name][$action] ?? null)
        {
            $closure = static function ($instance, ...$params) use ($method)
            {
                return $instance->$method(...$params);
            };
        }
        elseif (in_array($property = $this->PropertyMap[$_name] ?? $name, $this->Properties))
        {
            if ($this->checkReadable($property, $action) &&
                $this->checkWritable($property, $action))
            {
                switch ($action)
                {
                    case self::ACTION_SET:
                        $closure = static function ($instance, $value) use ($property) { $instance->$property = $value; };
                        break;

                    case self::ACTION_GET:
                        $closure = static function ($instance) use ($property) { return $instance->$property; };
                        break;

                    case self::ACTION_ISSET:
                        $closure = static function ($instance) use ($property) { return isset($instance->$property); };
                        break;

                    case self::ACTION_UNSET:
                        // Removal of a declared property is unlikely to be the
                        // intended outcome, so assign null instead of unsetting
                        $closure = static function ($instance) use ($property) { $instance->$property = null; };
                        break;
                }
            }
        }
        elseif ($this->IsExtensible)
        {
            $method  = $action == self::ACTION_ISSET ? "isMetaPropertySet" : $action . "MetaProperty";
            $closure = static function ($instance, ...$params) use ($method, $name)
            {
                return $instance->$method($name, ...$params);
            };
        }

        if (!$closure)
        {
            throw new RuntimeException("Unable to perform '$action' on property '$name'");
        }

        $closure = $closure->bindTo(null, $this->Class);

        return $this->PropertyActionClosures[$_name][$action] = $closure;
    }

    final public function getSerializeClosure(?ISerializeRules $rules = null): Closure
    {
        $rules = ($rules
            ? [$rules->getSort(), $this->IsExtensible && $rules->getIncludeMeta()]
            : [true, $this->IsExtensible]);
        $key = implode("\000", $rules);

        if ($closure = $this->SerializeClosures[$key] ?? null)
        {
            return $closure;
        }

        [$sort, $includeMeta] = $rules;
        $props = $this->ReadableProperties ?: $this->PublicProperties;
        $props = array_combine(
            $this->maybeNormalise($props, false, true),
            $props
        );
        if ($sort)
        {
            ksort($props);
        }

        $closure = static function ($instance) use ($props)
        {
            $arr = [];
            foreach ($props as $key => $prop)
            {
                $arr[$key] = $instance->$prop;
            }
            return $arr;
        };

        if ($includeMeta)
        {
            $closure = static function (IExtensible $instance) use ($closure)
            {
                $meta = $instance->getMetaProperties();
                return ($meta ? ["@meta" => $meta] : []) + $closure($instance);
            };
        }

        return $this->SerializeClosures[$key] = $closure;
    }

    private function checkReadable(string $property, string $action): bool
    {
        if (!$this->IsReadable || !in_array($action, [self::ACTION_GET, self::ACTION_ISSET]))
        {
            return true;
        }

        return in_array($property, $this->ReadableProperties);
    }

    private function checkWritable(string $property, string $action): bool
    {
        if (!$this->IsWritable || !in_array($action, [self::ACTION_SET, self::ACTION_UNSET]))
        {
            return true;
        }

        return in_array($property, $this->WritableProperties);
    }

}
