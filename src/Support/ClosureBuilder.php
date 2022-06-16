<?php

declare(strict_types=1);

namespace Lkrms\Support;

use Closure;
use Lkrms\Container\FactoryContainer;
use Lkrms\Contract\IConstructible;
use Lkrms\Contract\IExtensible;
use Lkrms\Contract\IReadable;
use Lkrms\Contract\IProvidable;
use Lkrms\Contract\IProvider;
use Lkrms\Contract\IResolvable;
use Lkrms\Contract\IWritable;
use Lkrms\Concern\TConstructible;
use Lkrms\Concern\TExtensible;
use Lkrms\Concern\TReadable;
use Lkrms\Concern\TProvidable;
use Lkrms\Concern\TResolvable;
use Lkrms\Concern\TWritable;
use Lkrms\Util\Reflect;
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
     * Add missing and unmapped values to the output
     */
    public const SKIP_NONE = 0;

    /**
     * If input values are missing, don't add them to the output
     */
    public const SKIP_MISSING = 1;

    /**
     * If input values are null, don't add them to the output
     */
    public const SKIP_NULL = 2;

    /**
     * If input values haven't been mapped to output values, discard them
     */
    public const SKIP_UNMAPPED = 4;

    /**
     * @var string
     */
    protected $Class;

    /**
     * @var bool
     */
    protected $IsGettable;

    /**
     * @var bool
     */
    protected $IsSettable;

    /**
     * @var bool
     */
    protected $IsExtensible;

    /**
     * @var bool
     */
    protected $IsProvidable;

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
     * Gettable property names
     *
     * @var string[]
     */
    protected $GettableProperties = [];

    /**
     * Settable property names
     *
     * @var string[]
     */
    protected $SettableProperties = [];

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
     * @var Closure|null
     */
    private $CreateFromClosure;

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
     * Signatures => closures
     *
     * @var array<string,Closure>
     */
    private static $ArrayMappers = [];

    public static function getBound(?Container $container, string $class): ClosureBuilder
    {
        if (is_null($container))
        {
            return self::get($class);
        }
        elseif ($container instanceof \Lkrms\Container\Container)
        {
            return self::get($container->name($class));
        }
        return self::get(get_class($container->get($class)));
    }

    public static function get(string $class): ClosureBuilder
    {
        if ($instance = self::$Instances[$class] ?? null)
        {
            return $instance;
        }

        $instance = new self($class);
        self::$Instances[$class] = $instance;

        return $instance;
    }

    protected function __construct(string $class)
    {
        $class         = new ReflectionClass($class);
        $providable    = $class->implementsInterface(IProvidable::class);
        $constructible = $providable || $class->implementsInterface(IConstructible::class);
        $extensible    = $class->implementsInterface(IExtensible::class);
        $gettable      = $class->implementsInterface(IReadable::class);
        $settable      = $class->implementsInterface(IWritable::class);
        $resolvable    = $class->implementsInterface(IResolvable::class);

        // If the class hasn't implemented any of these interfaces, perform a
        // (slower) check using traits
        if (!($constructible | $extensible | $gettable | $settable | $resolvable))
        {
            $traits        = Reflect::getAllTraits($class);
            $providable    = array_key_exists(TProvidable::class, $traits);
            $constructible = $providable || array_key_exists(TConstructible::class, $traits);
            $extensible    = array_key_exists(TExtensible::class, $traits);
            $gettable      = array_key_exists(TReadable::class, $traits);
            $settable      = array_key_exists(TWritable::class, $traits);
            $resolvable    = array_key_exists(TResolvable::class, $traits);
        }

        $this->Class        = $class->name;
        $this->IsGettable   = $gettable;
        $this->IsSettable   = $settable;
        $this->IsExtensible = $extensible;
        $this->IsProvidable = $providable;

        $propertyFilter = 0;
        $methodFilter   = 0;

        // IReadable and ISettable provide access to protected and "magic"
        // properties
        if ($gettable || $settable)
        {
            $propertyFilter |= ReflectionProperty::IS_PROTECTED;
            $methodFilter   |= ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
        }

        // IResolvable provides access to properties via alternative names
        if ($resolvable)
        {
            $propertyFilter  |= ReflectionProperty::IS_PUBLIC;
            $this->Normaliser = Closure::fromCallable("{$this->Class}::normaliseProperty");
        }

        // Get [non-static] declared properties
        if ($propertyFilter)
        {
            $properties = array_values(array_filter(
                $class->getProperties($propertyFilter),
                function (ReflectionProperty $prop) { return !$prop->isStatic(); }
            ));
            $this->Properties = Reflect::getNames($properties);

            if ($propertyFilter & ReflectionProperty::IS_PUBLIC)
            {
                if ($propertyFilter & ReflectionProperty::IS_PROTECTED)
                {
                    $this->PublicProperties = Reflect::getNames(array_values(array_filter(
                        $properties,
                        function (ReflectionProperty $prop) { return $prop->isPublic(); }
                    )));
                }
                else
                {
                    $this->PublicProperties = $this->Properties;
                }
            }

            if ($gettable)
            {
                $properties = array_merge($class->getMethod("getGettable")->invoke(null), $this->PublicProperties);
                $this->GettableProperties = (["*"] === $properties)
                    ? $this->Properties
                    : array_intersect($this->Properties, $properties ?: []);
            }

            if ($settable)
            {
                $properties = array_merge($class->getMethod("getSettable")->invoke(null), $this->PublicProperties);
                $this->SettableProperties = (["*"] === $properties)
                    ? $this->Properties
                    : array_intersect($this->Properties, $properties ?: []);
            }
        }

        // Get [non-static] "magic" properties, e.g. _get<Property>()
        if ($methodFilter)
        {
            $actions = [];
            if ($gettable)
            {
                array_push($actions, "get", "isset");
            }
            if ($settable)
            {
                array_push($actions, "set", "unset");
            }
            $regex = '/^_(' . implode("|", $actions) . ')(.+)$/i';

            foreach ($class->getMethods($methodFilter) as $method)
            {
                if ($method->isStatic() || !preg_match($regex, $method->name, $matches))
                {
                    continue;
                }

                list ($property, $action) = [$matches[2], strtolower($matches[1])];

                if (!array_key_exists($property, $this->Methods))
                {
                    $this->Methods[$property] = [];
                }

                $this->Methods[$property][$action] = $method->name;
            }
        }

        // Get constructor parameters
        if ($constructible && ($constructor = $class->getConstructor()))
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
     * @return Closure
     * ```php
     * // If the class implements IProvidable:
     * closure(\Psr\Container\ContainerInterface $container, \Lkrms\Contract\IProvider $provider, array $array, callable $callback = null)
     * // Otherwise:
     * closure(array $array, callable $callback = null, \Psr\Container\ContainerInterface $container = null)
     * ```
     */
    public function getCreateFromSignatureClosure(array $keys): Closure
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
                if ($this->checkSettable($property, "set"))
                {
                    $propertyKeys[$key] = $property;
                }
            }
            elseif ($this->IsExtensible)
            {
                $metaKeys[] = $key;
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

        $closure = function (array $array, callable $callback = null, Container $container = null, IProvider $provider = null) use ($closure)
        {
            if (!$container)
            {
                $container = new FactoryContainer();
            }

            if ($callback)
            {
                $array = $callback($array);
            }

            return $closure($container, $array, $provider);
        };

        if ($this->IsProvidable)
        {
            // Return a closure where $container and $provider are not optional
            $closure = function (Container $container, IProvider $provider, array $array, callable $callback = null) use ($closure)
            {
                return $closure($array, $callback, $container, $provider);
            };
        }

        $this->CreateFromSignatureClosures[$sig] = $closure;

        return $closure;
    }

    /**
     * @return Closure
     * ```php
     * // If the class implements IProvidable:
     * closure(\Psr\Container\ContainerInterface $container, \Lkrms\Contract\IProvider $provider, array $array, callable $callback = null)
     * // Otherwise:
     * closure(array $array, callable $callback = null, \Psr\Container\ContainerInterface $container = null)
     * ```
     */
    public function getCreateFromClosure(): Closure
    {
        if ($closure = $this->CreateFromClosure)
        {
            return $closure;
        }

        if ($this->IsProvidable)
        {
            $closure = function (Container $container, IProvider $provider, array $array, callable $callback = null)
            {
                if ($callback)
                {
                    $array = $callback($array);
                }

                $keys = array_keys($array);

                return ($this->getCreateFromSignatureClosure($keys))($container, $provider, $array);
            };
        }
        else
        {
            $closure = function (array $array, callable $callback = null, Container $container = null)
            {
                if ($callback)
                {
                    $array = $callback($array);
                }

                $keys = array_keys($array);

                return ($this->getCreateFromSignatureClosure($keys))($array, null, $container);
            };
        }

        $this->CreateFromClosure = $closure;

        return $closure;
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
            if ($this->checkGettable($property, $action) &&
                $this->checkSettable($property, $action))
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

        $props = $this->GettableProperties ?: $this->PublicProperties;

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

    private function checkGettable(string $property, string $action): bool
    {
        if (!$this->IsGettable || !in_array($action, ["get", "isset"]))
        {
            return true;
        }

        return in_array($property, $this->GettableProperties);
    }

    private function checkSettable(string $property, string $action): bool
    {
        if (!$this->IsSettable || !in_array($action, ["set", "unset"]))
        {
            return true;
        }

        return in_array($property, $this->SettableProperties);
    }

    /**
     * Get a static closure to move array values from one set of keys to another
     *
     * Closure signature:
     *
     * ```php
     * static function (array $in): array
     * ```
     *
     * @param array<int|string,int|string> $keyMap An array that maps input keys
     * to output keys.
     * @param bool $sameKeys If `true`, improve performance by assuming every
     * input array has the same keys in the same order as in `$keyMap`.
     * @param int $skip A bitmask of `ClosureBuilder::SKIP_*` values.
     * @return Closure
     */
    public static function getArrayMapper(
        array $keyMap,
        bool $sameKeys = false,
        int $skip      = ClosureBuilder::SKIP_MISSING | ClosureBuilder::SKIP_UNMAPPED
    ): Closure
    {
        $sig = implode("\000", array_merge(
            array_keys($keyMap),
            array_values($keyMap),
            [$sameKeys, $skip]
        ));

        if ($closure = self::$ArrayMappers[$sig] ?? null)
        {
            return $closure;
        }

        if ($sameKeys)
        {
            $outKeys = array_values($keyMap);
            $closure = static function (array $in) use ($outKeys): array
            {
                $out = array_combine($outKeys, $in);

                if ($out === false)
                {
                    throw new UnexpectedValueException("Invalid input array");
                }

                return $out;
            };
        }
        else
        {
            $closure = static function (array $in) use ($keyMap): array
            {
                $out = [];

                foreach ($in as $key => $value)
                {
                    $out[$keyMap[$key] ?? $key] = $value;
                }

                return $out;
            };

            if ($skip & self::SKIP_UNMAPPED)
            {
                $closure = static function (array $in) use ($keyMap, $closure): array
                {
                    // Only keep mapped values
                    $in = array_intersect_key($in, $keyMap);

                    return $closure($in);
                };
            }
            else
            {
                $flipped = array_flip($keyMap);
                $closure = static function (array $in) use ($keyMap, $flipped, $closure): array
                {
                    // In addition to mapped values, keep values that aren't
                    // mapped (`array_diff_key($in, $keyMap...`) and don't have
                    // the same key as a mapped output key (`...$flipped`)
                    $in = array_intersect_key($in, $keyMap + array_diff_key($in, $keyMap, $flipped));

                    return $closure($in);
                };
            }

            if (!($skip & self::SKIP_MISSING))
            {
                $closure = static function (array $in) use ($keyMap, $closure): array
                {
                    $missing = array_diff_key($keyMap, $in);

                    return $closure($in) + (array_combine(
                        array_values($missing),
                        array_fill(0, count($missing), null)
                    ) ?: []);
                };
            }
        }

        if ($skip & self::SKIP_NULL)
        {
            $closure = static function (array $in) use ($closure): array
            {
                return array_filter(
                    $closure($in),
                    function ($value) { return !is_null($value); }
                );
            };
        }

        self::$ArrayMappers[$sig] = $closure;

        return $closure;
    }
}
