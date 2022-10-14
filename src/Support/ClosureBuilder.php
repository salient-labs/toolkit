<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IHierarchy;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvidableContext;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IResolvable;
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
    /**
     * @var string
     */
    private $Class;

    /**
     * @var string|null
     */
    private $BaseClass;

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
     * Property names
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
     * @var string[]
     */
    private $ReadableProperties = [];

    /**
     * Writable property names
     *
     * @var string[]
     */
    private $WritableProperties = [];

    /**
     * "Magic" property names => supported actions => method names
     *
     * @var array<string,array<string,string>>
     */
    private $Methods = [];

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
     * Normalised constructor parameter names => class names
     *
     * @var array<string,string>
     */
    private $ServiceMap = [];

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
     * @var Closure|null
     */
    private $SerializeClosure;

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
        $instance->BaseClass = $class;

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

        // IResolvable provides access to properties via alternative names
        if ($class->implementsInterface(IResolvable::class))
        {
            $this->Normaliser = $class->getMethod("getNormaliser")->invoke(null);
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
                $actions[] = "get";
                $actions[] = "isset";
            }
            if ($this->IsWritable)
            {
                $actions[] = "set";
                $actions[] = "unset";
            }
            $regex = '/^_(?P<action>' . implode("|", $actions) . ')(?P<property>.+)$/i';

            foreach ($class->getMethods($methodFilter) as $method)
            {
                if (!$method->isStatic() && preg_match($regex, $method->name, $match))
                {
                    $this->Methods[$match["property"]][strtolower($match["action"])] = $method->name;
                }
            }
        }

        // Get constructor parameters
        if (($constructor = $class->getConstructor()) && $constructor->isPublic())
        {
            foreach ($constructor->getParameters() as $param)
            {
                $normalised   = $this->maybeNormalise($param->name);
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
                        $this->ServiceMap[$normalised] = $type->getName();
                    }
                }
                $this->Parameters[]       = $this->ParameterMap[$normalised] = $param->name;
                $this->DefaultArguments[] = $defaultValue;
            }
            $this->ParametersIndex = array_flip($this->Parameters);
        }

        // Create normalised property and parameter name maps
        $methodProperties = array_keys($this->Methods);
        if ($this->Normaliser)
        {
            $this->PropertyMap = array_combine(
                array_map($this->Normaliser, $this->Properties),
                $this->Properties
            );
            $this->MethodMap = array_combine(
                array_map($this->Normaliser, $methodProperties),
                $methodProperties
            );
        }
        else
        {
            $this->PropertyMap = array_combine($this->Properties, $this->Properties);
            $this->MethodMap   = array_combine($methodProperties, $methodProperties);
        }
    }

    /**
     * @param string|string[] $value
     * @return string|string[]
     */
    final public function maybeNormalise($value)
    {
        if (!$this->Normaliser)
        {
            return $value;
        }
        if (is_array($value))
        {
            return array_map($this->Normaliser, $value);
        }
        return ($this->Normaliser)($value);
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
     * closure(array $array, ?\Lkrms\Contract\IContainer $container = null, ?\Lkrms\Contract\IHierarchy $parent = null)
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
        $closure = static function (array $array, ?IContainer $container = null, ?IHierarchy $parent = null) use ($closure)
        {
            return $closure($container, $array, null, null, $parent);
        };

        $this->CreateProviderlessFromSignatureClosures[$sig][(int)$strict] = $closure;
        if ($strict)
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
                $parent);
        };

        $this->CreateProvidableFromSignatureClosures[$sig][(int)$strict] = $closure;
        if ($strict)
        {
            $this->CreateProvidableFromSignatureClosures[$sig][(int)false] = $closure;
        }

        return $closure;
    }

    /**
     * @return Closure
     * ```
     * closure(?\Lkrms\Contract\IContainer $container, array $array, ?\Lkrms\Contract\IProvider $provider, ?\Lkrms\Contract\IProvidableContext $context, ?\Lkrms\Contract\IHierarchy $parent)
     * ```
     */
    private function _getCreateFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\000", $keys);

        if ($closure = $this->CreateFromSignatureClosures[$sig] ?? null)
        {
            return $closure;
        }

        // 1. Normalise array keys (i.e. field/property names)
        if ($this->Normaliser)
        {
            $keys = array_combine(
                array_map($this->Normaliser, $keys),
                $keys
            );
        }
        else
        {
            $keys = array_combine($keys, $keys);
        }

        // 2. Check for required parameters that haven't been provided
        if (!empty($missing = array_diff_key(
            $this->RequiredMap,
            $this->ServiceMap,
            $keys
        )))
        {
            throw new UnexpectedValueException("{$this->Class} constructor requires " . implode(", ", $missing));
        }

        // 3. Resolve $keys to:
        // - constructor parameters ($parameterKeys)
        // - "magic" property methods ($methodKeys)
        // - properties ($propertyKeys)
        // - arbitrary properties ($metaKeys)
        $parameterKeys = $methodKeys = $propertyKeys = $metaKeys = [];

        foreach ($keys as $normalisedKey => $key)
        {
            if ($param = $this->ParameterMap[$normalisedKey] ?? null)
            {
                $parameterKeys[$key] = $this->ParametersIndex[$param];
            }
            elseif ($method = $this->Methods[$this->MethodMap[$normalisedKey] ?? $key]["set"] ?? null)
            {
                $methodKeys[$key] = $method;
            }
            elseif ($property = $this->PropertyMap[$normalisedKey] ?? null)
            {
                if ($this->checkWritable($property, "set"))
                {
                    $propertyKeys[$key] = $property;
                }
            }
            elseif ($this->IsExtensible)
            {
                $metaKeys[] = $key;
            }
            elseif ($strict)
            {
                throw new UnexpectedValueException("No matching property or constructor parameter found in {$this->Class} for '$key'");
            }
        }

        // 4. Build the smallest possible chain of closures
        if ($parameterKeys)
        {
            $closure = function (?IContainer $container, array $array) use ($parameterKeys)
            {
                $args = $this->DefaultArguments;
                foreach ($parameterKeys as $key => $index)
                {
                    $args[$index] = $array[$key];
                }
                if ($container)
                {
                    return $container->get($this->Class, ...$args);
                }
                return new $this->Class(...$args);
            };
        }
        else
        {
            $closure = function (?IContainer $container)
            {
                if ($container)
                {
                    return $container->get($this->Class, ...$this->DefaultArguments);
                }
                return new $this->Class(...$this->DefaultArguments);
            };
        }

        if ($propertyKeys)
        {
            $closure = static function (?IContainer $container, array $array) use ($closure, $propertyKeys)
            {
                $obj = $closure($container, $array);
                foreach ($propertyKeys as $key => $property)
                {
                    $obj->$property = $array[$key];
                }
                return $obj;
            };
            $closure = $closure->bindTo(null, $this->Class);
        }

        // Call `setProvider()` and/or `setParent()` early in case property
        // methods need them
        if ($this->IsProvidable)
        {
            $closure = function (?IContainer $container, array $array, ?IProvider $provider, ?IProvidableContext $context) use ($closure)
            {
                /** @var IProvidable $obj */
                $obj = $closure($container, $array);
                if ($provider)
                {
                    return $obj->setProvider($provider, $this->BaseClass ?: $this->Class)
                        ->setProvidableContext($context);
                }
                return $obj;
            };
        }

        if ($this->IsHierarchy)
        {
            $closure = static function (?IContainer $container, array $array, ?IProvider $provider, ?IProvidableContext $context, ?IHierarchy $parent) use ($closure)
            {
                /** @var IHierarchy $obj */
                $obj = $closure($container, $array, $provider, $context);
                if ($parent)
                {
                    return $obj->setParent($parent);
                }
                return $obj;
            };
        }

        if ($methodKeys)
        {
            $closure = static function (?IContainer $container, array $array, ?IProvider $provider, ?IProvidableContext $context, ?IHierarchy $parent) use ($closure, $methodKeys)
            {
                $obj = $closure($container, $array, $provider, $context, $parent);
                foreach ($methodKeys as $key => $method)
                {
                    $obj->$method($array[$key]);
                }
                return $obj;
            };
            $closure = $closure->bindTo(null, $this->Class);
        }

        if ($metaKeys)
        {
            $closure = static function (?IContainer $container, array $array, ?IProvider $provider, ?IProvidableContext $context, ?IHierarchy $parent) use ($closure, $metaKeys)
            {
                $obj = $closure($container, $array, $provider, $context, $parent);
                foreach ($metaKeys as $key)
                {
                    $obj->setMetaProperty((string)$key, $array[$key]);
                }
                return $obj;
            };
        }

        return $this->CreateFromSignatureClosures[$sig] = $closure;
    }

    /**
     * @param bool $strict If `true`, return a closure that throws an exception
     * if `$array` contains unusable values.
     * @return Closure
     * ```php
     * closure(array $array, ?\Lkrms\Contract\IContainer $container = null, ?\Lkrms\Contract\IHierarchy $parent = null)
     * ```
     */
    final public function getCreateFromClosure(bool $strict = false): Closure
    {
        if ($closure = $this->CreateProviderlessFromClosures[(int)$strict] ?? null)
        {
            return $closure;
        }

        $closure = function (array $array, ?IContainer $container = null, ?IHierarchy $parent = null) use ($strict)
        {
            $keys = array_keys($array);
            return ($this->getCreateFromSignatureClosure($keys, $strict))($array, $container, $parent);
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
     * @param string $action Either `"set"`, `"get"`, `"isset"` or `"unset"`.
     * @return Closure
     */
    final public function getPropertyActionClosure(string $name, string $action): Closure
    {
        $_name = $this->maybeNormalise($name);

        if ($closure = $this->PropertyActionClosures[$_name][$action] ?? null)
        {
            return $closure;
        }

        if (!in_array($action, ["set", "get", "isset", "unset"]))
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
                    case "set":
                        $closure = static function ($instance, $value) use ($property) { $instance->$property = $value; };
                        break;

                    case "get":
                        $closure = static function ($instance) use ($property) { return $instance->$property; };
                        break;

                    case "isset":
                        $closure = static function ($instance) use ($property) { return isset($instance->$property); };
                        break;

                    case "unset":
                        // Removal of a declared property is unlikely to be the
                        // intended outcome, so assign null instead of unsetting
                        $closure = static function ($instance) use ($property) { $instance->$property = null; };
                        break;
                }
            }
        }
        elseif ($this->IsExtensible)
        {
            $method  = $action == "isset" ? "isMetaPropertySet" : $action . "MetaProperty";
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

    final public function getSerializeClosure(): Closure
    {
        if ($closure = $this->SerializeClosure)
        {
            return $closure;
        }

        $props = $this->ReadableProperties ?: $this->PublicProperties;
        $props = array_combine(
            $this->maybeNormalise($props),
            $props
        );

        $closure = static function ($instance) use ($props)
        {
            $arr = [];
            foreach ($props as $key => $prop)
            {
                $arr[$key] = $instance->$prop;
            }
            return $arr;
        };

        return $this->SerializeClosure = $closure;
    }

    private function checkReadable(string $property, string $action): bool
    {
        if (!$this->IsReadable || !in_array($action, ["get", "isset"]))
        {
            return true;
        }

        return in_array($property, $this->ReadableProperties);
    }

    private function checkWritable(string $property, string $action): bool
    {
        if (!$this->IsWritable || !in_array($action, ["set", "unset"]))
        {
            return true;
        }

        return in_array($property, $this->WritableProperties);
    }

}
