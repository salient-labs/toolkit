<?php

declare(strict_types=1);

namespace Lkrms\Reflect;

use Closure;
use Lkrms\Convert;
use Lkrms\Ioc\Ioc;
use Lkrms\Reflect;
use Lkrms\Template\IAccessible;
use Lkrms\Template\IConstructible;
use Lkrms\Template\IExtensible;
use Lkrms\Template\IGettable;
use Lkrms\Template\IResolvable;
use Lkrms\Template\ISettable;
use Lkrms\Template\TConstructible;
use Lkrms\Template\TExtensible;
use Lkrms\Template\TGettable;
use Lkrms\Template\TSettable;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use UnexpectedValueException;

/**
 *
 * @package Lkrms
 */
class PropertyResolver
{
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
     * Constructor parameter names => constructor argument indices
     *
     * @var array<string,int>
     */
    protected $ParametersIndex = [];

    /**
     * Converts property names to normalised property names
     *
     * @var callable
     */
    protected $Normaliser;

    /**
     * @var array<string,array<string,Closure>>
     */
    private $PropertyActionClosures = [];

    /**
     * @var Closure
     */
    private $CreateFromClosure;

    /**
     * @var array<string,Closure>
     */
    private $CreateFromSignatureClosures = [];

    private static $Instances = [];

    public static function getFor(string $class): PropertyResolver
    {
        $class = Ioc::resolve($class);

        if (is_null($instance = self::$Instances[$class] ?? null))
        {
            $instance = new self($class);
            self::$Instances[$class] = $instance;
        }

        return $instance;
    }

    protected function __construct(string $class)
    {
        $class         = new ReflectionClass($class);
        $constructible = $class->implementsInterface(IConstructible::class);
        $extensible    = $class->implementsInterface(IExtensible::class);
        $gettable      = $extensible || $class->implementsInterface(IGettable::class);
        $settable      = $extensible || $class->implementsInterface(ISettable::class);
        $resolvable    = $extensible || $class->implementsInterface(IResolvable::class);

        // If the class hasn't implemented any of these interfaces, perform a
        // (slower) check using traits
        if (!($constructible | $gettable | $settable | $resolvable))
        {
            $traits        = Reflect::getAllTraits($class);
            $constructible = array_key_exists(TConstructible::class, $traits);
            $extensible    = array_key_exists(TExtensible::class, $traits);
            $gettable      = $extensible || array_key_exists(TGettable::class, $traits);
            $settable      = $extensible || array_key_exists(TSettable::class, $traits);
            $resolvable    = $extensible;
        }

        $this->Class        = $class->name;
        $this->IsGettable   = $gettable;
        $this->IsSettable   = $settable;
        $this->IsExtensible = $extensible;

        $propertyFilter = 0;
        $methodFilter   = 0;

        // IGettable and ISettable provide access to protected and "magic"
        // properties
        if ($gettable || $settable)
        {
            $propertyFilter |= ReflectionProperty::IS_PROTECTED;
            $methodFilter   |= ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED;
        }

        // IConstructible and IResolvable both provide access to properties via
        // alternative names
        if ($constructible || $resolvable)
        {
            $propertyFilter |= ReflectionProperty::IS_PUBLIC;

            if ($resolvable)
            {
                $this->Normaliser = Closure::fromCallable("{$this->Class}::normalisePropertyName");
            }
            else
            {
                $this->Normaliser = function (string $name) { return Convert::toSnakeCase($name); };
            }
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
                $this->GettableProperties = (IAccessible::ALLOW_PROTECTED === $properties)
                    ? $this->Properties
                    : array_intersect($this->Properties, $properties ?: []);
            }

            if ($settable)
            {
                $properties = array_merge($class->getMethod("getSettable")->invoke(null), $this->PublicProperties);
                $this->SettableProperties = (IAccessible::ALLOW_PROTECTED === $properties)
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
                $normalised   = $this->maybeNormalise($param->name);
                $defaultValue = null;

                if ($param->isOptional())
                {
                    $defaultValue = $param->getDefaultValue();
                }
                elseif (!$param->allowsNull())
                {
                    $this->RequiredParameters[] = $this->RequiredMap[$normalised] = $param->name;
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

    protected function maybeNormalise(string $name): string
    {
        return $this->Normaliser ? ($this->Normaliser)($name) : $name;
    }

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

        /**
         * @todo Create a chain of closures
         */
        $closure = function (array $array, callable $callback = null) use ($parameterKeys, $methodKeys, $propertyKeys, $metaKeys)
        {
            if ($callback)
            {
                $array = $callback($array);
            }

            $args = $this->DefaultArguments;

            foreach ($parameterKeys as $key => $index)
            {
                $args[$index] = $array[$key];
            }

            $obj = Ioc::create($this->Class, $args);

            foreach ($methodKeys as $key => $method)
            {
                $obj->$method($array[$key]);
            }

            foreach ($propertyKeys as $key => $property)
            {
                $obj->$property = $array[$key];
            }

            foreach ($metaKeys as $key)
            {
                $obj->setMetaProperty($key, $array[$key]);
            }

            return $obj;
        };

        $this->CreateFromSignatureClosures[$sig] = $closure;

        return $closure;
    }

    public function getCreateFromClosure(): Closure
    {
        if ($closure = $this->CreateFromClosure)
        {
            return $closure;
        }

        $closure = function (array $array, callable $callback = null)
        {
            $keys = array_keys($array);

            return ($this->getCreateFromSignatureClosure($keys))($array, $callback);
        };

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

        if (!$closure || is_null($closure = $closure->bindTo(null, $this->Class)))
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
}
