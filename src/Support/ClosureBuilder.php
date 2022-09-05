<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use Lkrms\Container\FactoryContainer;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IResolvable;
use Lkrms\Contract\ITreeNode;
use Lkrms\Contract\IWritable;
use Lkrms\Facade\Reflect;
use Psr\Container\ContainerInterface as Container;
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
    protected $Class;

    /**
     * @var bool
     */
    protected $IsReadable;

    /**
     * @var bool
     */
    protected $IsWritable;

    /**
     * @var bool
     */
    protected $IsExtensible;

    /**
     * @var bool
     */
    protected $IsProvidable;

    /**
     * @var bool
     */
    protected $IsTreeNode;

    /**
     * Property names
     *
     * @var string[]
     */
    protected $Properties = [];

    /**
     * Public property names
     *
     * @var string[]
     */
    protected $PublicProperties = [];

    /**
     * Readable property names
     *
     * @var string[]
     */
    protected $ReadableProperties = [];

    /**
     * Writable property names
     *
     * @var string[]
     */
    protected $WritableProperties = [];

    /**
     * "Magic" property names => supported actions => method names
     *
     * @var array<string,array<string,string>>
     */
    protected $Methods = [];

    /**
     * Constructor parameter names, in order of appearance
     *
     * @var string[]
     */
    protected $Parameters = [];

    /**
     * Parameters that aren't nullable and don't have a default value
     *
     * @var string[]
     */
    protected $RequiredParameters = [];

    /**
     * Default constructor arguments
     *
     * @var array
     */
    protected $DefaultArguments = [];

    /**
     * Normalised property names => declared property names
     *
     * @var array<string,string>
     */
    protected $PropertyMap = [];

    /**
     * Normalised property names => "magic" property names
     *
     * @var array<string,string>
     */
    protected $MethodMap = [];

    /**
     * Normalised constructor parameter names => constructor parameter names
     *
     * @var array<string,string>
     */
    protected $ParameterMap = [];

    /**
     * Normalised constructor parameter names => constructor parameter names
     *
     * @var array<string,string>
     */
    protected $RequiredMap = [];

    /**
     * Normalised constructor parameter names => class names
     *
     * @var array<string,string>
     */
    protected $ServiceMap = [];

    /**
     * Constructor parameter names => constructor argument indices
     *
     * @var array<string,int>
     */
    protected $ParametersIndex = [];

    /**
     * Converts property names to normalised property names
     *
     * @var callable|null
     */
    protected $Normaliser;

    /**
     * @var array<string,array<string,Closure>>
     */
    private $PropertyActionClosures = [];

    /**
     * @var array<int,Closure>
     */
    private $CreateFromClosures = [];

    /**
     * @var array<string,Closure>
     */
    private $CreateFromSignatureClosures = [];

    /**
     * @var Closure|null
     */
    private $SerializeClosure;

    /**
     * @var array<string,ClosureBuilder>
     */
    private static $Instances = [];

    /**
     * Return a ClosureBuilder for $class after using $container to resolve it
     * to a concrete class
     */
    public static function getBound(?Container $container, string $class): ClosureBuilder
    {
        if (is_null($container))
        {
            return self::get($class);
        }
        elseif ($container instanceof \Lkrms\Container\Container)
        {
            return self::get($container->getName($class));
        }
        return self::get(get_class($container->get($class)));
    }

    /**
     * Return a ClosureBuilder for $class
     */
    public static function get(string $class): ClosureBuilder
    {
        return self::$Instances[$class]
            ?? (self::$Instances[$class] = new self($class));
    }

    protected function __construct(string $class)
    {
        $class              = new ReflectionClass($class);
        $this->Class        = $class->name;
        $this->IsReadable   = $class->implementsInterface(IReadable::class);
        $this->IsWritable   = $class->implementsInterface(IWritable::class);
        $this->IsExtensible = $class->implementsInterface(IExtensible::class);
        $this->IsProvidable = $class->implementsInterface(IProvidable::class);
        $this->IsTreeNode   = $class->implementsInterface(ITreeNode::class);

        // IResolvable provides access to properties via alternative names
        if ($class->implementsInterface(IResolvable::class))
        {
            $this->Normaliser = Closure::fromCallable("{$this->Class}::normaliseProperty");
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
                ["*"] === $readable ? $this->Properties : ($readable ?: []),
                $this->PublicProperties
            );
            $this->ReadableProperties = array_intersect($this->Properties, $readable);
        }

        if ($this->IsWritable)
        {
            $writable = $class->getMethod("getWritable")->invoke(null);
            $writable = array_merge(
                ["*"] === $writable ? $this->Properties : ($writable ?: []),
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
                $normalised   = $this->maybeNormaliseProperty($param->name);
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

    public function maybeNormaliseProperty(string $name): string
    {
        return $this->Normaliser ? ($this->Normaliser)($name) : $name;
    }

    /**
     * @param bool $strict If set, throw an exception instead of discarding
     * unusable data.
     * @return Closure
     * ```php
     * // If the class implements IProvidable:
     * closure(\Psr\Container\ContainerInterface $container, \Lkrms\Contract\IProvider $provider, array $array, callable $callback = null, \Lkrms\Contract\ITreeNode $parent = null)
     * // Otherwise:
     * closure(array $array, callable $callback = null, \Psr\Container\ContainerInterface $container = null, \Lkrms\Contract\ITreeNode $parent = null)
     * ```
     */
    public function getCreateFromSignatureClosure(array $keys, bool $strict = false): Closure
    {
        $sig = implode("\000", $keys);

        // Use a cached closure if this array signature has already been
        // resolved, otherwise create a closure for this and future runs
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
            $closure = function (Container $container, array $array) use ($parameterKeys)
            {
                $args = $this->DefaultArguments;
                foreach ($parameterKeys as $key => $index)
                {
                    $args[$index] = $array[$key];
                }
                return $container->get($this->Class, ...$args);
            };
        }
        else
        {
            $closure = function (Container $container)
            {
                return $container->get($this->Class, ...$this->DefaultArguments);
            };
        }

        if ($propertyKeys)
        {
            $closure = static function (Container $container, array $array) use ($closure, $propertyKeys)
            {
                $obj = $closure($container, $array);
                foreach ($propertyKeys as $key => $property)
                {
                    $obj->$property = $array[$key];
                }
                return $obj;
            };
            // We've already checked property access, so bypass
            // IWritable::__set() to speed things up
            $closure = $closure->bindTo(null, $this->Class);
        }

        // Call `setProvider()` early because property methods might need it
        if ($this->IsProvidable)
        {
            $closure = static function (Container $container, array $array, IProvider $provider) use ($closure)
            {
                $obj = $closure($container, $array);
                $obj->setProvider($provider);
                return $obj;
            };
        }

        if ($methodKeys)
        {
            $closure = static function (Container $container, array $array, ?IProvider $provider) use ($closure, $methodKeys)
            {
                $obj = $closure($container, $array, $provider);
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
            $closure = static function (Container $container, array $array, ?IProvider $provider) use ($closure, $metaKeys)
            {
                $obj = $closure($container, $array, $provider);
                foreach ($metaKeys as $key)
                {
                    $obj->setMetaProperty((string)$key, $array[$key]);
                }
                return $obj;
            };
        }

        if ($this->IsTreeNode)
        {
            $closure = static function (Container $container, array $array, ?IProvider $provider, ?ITreeNode $parent) use ($closure)
            {
                /** @var ITreeNode $obj */
                $obj = $closure($container, $array, $provider);
                if ($parent)
                {
                    $obj->setParent($parent);
                }
                return $obj;
            };
        }

        $closure = function (array $array, callable $callback = null, Container $container = null, IProvider $provider = null, ITreeNode $parent = null) use ($closure)
        {
            if ($callback)
            {
                $array = $callback($array);
            }
            return $closure($container ?: new FactoryContainer(), $array, $provider, $parent);
        };

        if ($this->IsProvidable)
        {
            // Return a closure where $container and $provider are not optional
            $closure = function (Container $container, IProvider $provider, array $array, callable $callback = null, ITreeNode $parent = null) use ($closure)
            {
                return $closure($array, $callback, $container, $provider, $parent);
            };
        }

        return $this->CreateFromSignatureClosures[$sig] = $closure;
    }

    /**
     * @param bool $strict If set, throw an exception instead of discarding
     * unusable data.
     * @return Closure
     * ```php
     * // If the class implements IProvidable:
     * closure(\Psr\Container\ContainerInterface $container, \Lkrms\Contract\IProvider $provider, array $array, callable $callback = null, \Lkrms\Contract\ITreeNode $parent = null)
     * // Otherwise:
     * closure(array $array, callable $callback = null, \Psr\Container\ContainerInterface $container = null, \Lkrms\Contract\ITreeNode $parent = null)
     * ```
     */
    public function getCreateFromClosure(bool $strict = false): Closure
    {
        if ($closure = $this->CreateFromClosures[(int)$strict] ?? null)
        {
            return $closure;
        }
        if ($this->IsProvidable)
        {
            $closure = function (Container $container, IProvider $provider, array $array, callable $callback = null, ITreeNode $parent = null) use ($strict)
            {
                if ($callback)
                {
                    $array = $callback($array);
                }
                $keys = array_keys($array);
                return ($this->getCreateFromSignatureClosure($keys, $strict))($container, $provider, $array, null, $parent);
            };
        }
        else
        {
            $closure = function (array $array, callable $callback = null, Container $container = null, ITreeNode $parent = null) use ($strict)
            {
                if ($callback)
                {
                    $array = $callback($array);
                }
                $keys = array_keys($array);
                return ($this->getCreateFromSignatureClosure($keys, $strict))($array, null, $container, $parent);
            };
        }

        return $this->CreateFromClosures[(int)$strict] = $closure;
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
    public function getPropertyActionClosure(string $name, string $action): Closure
    {
        $_name = $this->maybeNormaliseProperty($name);

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

        if ($closure)
        {
            $closure = $closure->bindTo(null, $this->Class);
        }

        if (!$closure)
        {
            throw new RuntimeException("Unable to perform '$action' on property '$name'");
        }

        if (!array_key_exists($_name, $this->PropertyActionClosures))
        {
            $this->PropertyActionClosures[$_name] = [];
        }

        $this->PropertyActionClosures[$_name][$action] = $closure;

        return $closure;
    }

    public function getSerializeClosure(): Closure
    {
        if ($closure = $this->SerializeClosure)
        {
            return $closure;
        }

        $props = $this->ReadableProperties ?: $this->PublicProperties;

        if ($this->Normaliser)
        {
            $props = array_combine(
                array_map($this->Normaliser, $props),
                $props
            );
        }
        else
        {
            $props = array_combine($props, $props);
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

        $this->SerializeClosure = $closure;

        return $closure;
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
