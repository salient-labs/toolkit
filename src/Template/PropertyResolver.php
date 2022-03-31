<?php

declare(strict_types=1);

namespace Lkrms\Template;

use Closure;
use Lkrms\Convert;
use Lkrms\Reflect;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
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
     * @var string
     */
    protected $Action;

    /**
     * Property names
     *
     * @var string[]
     */
    protected $Properties = [];

    /**
     * Property names => "magic" methods
     *
     * @var array<string,string>
     */
    protected $Methods = [];

    /**
     * Normalised property names => declared property names
     *
     * @var array<string,string>
     */
    protected $PropertyMap = [];

    /**
     * Normalised property names => "magic" methods
     *
     * @var array<string,string>
     */
    protected $MethodMap = [];

    /**
     * Converts property names to normalised property names
     *
     * @var callable
     */
    protected $Normaliser;

    public function __construct(string $class, string $action, ?array $allowedProperties)
    {
        if (!in_array($action, ["set", "get", "isset", "unset"]))
        {
            throw new UnexpectedValueException("Invalid action: $action");
        }

        $class = new ReflectionClass($class);

        $this->Class  = $class->name;
        $this->Action = $action;

        $propertyFilter = ReflectionProperty::IS_PROTECTED;
        $methodFilter   = ReflectionMethod::IS_PROTECTED;

        if ($class->implementsInterface(IResolvable::class))
        {
            $propertyFilter  |= ReflectionProperty::IS_PUBLIC;
            $methodFilter    |= ReflectionMethod::IS_PUBLIC;
            $this->Normaliser = Closure::fromCallable([$class->name, "normalisePropertyName"]);
        }

        $re = '/^_' . $action . '(.+)$/i';

        // 1. Resolve declared properties
        $props = array_filter(
            $class->getProperties($propertyFilter),
            function (ReflectionProperty $prop) { return !$prop->isStatic(); }
        );
        $names = Reflect::getNames($props);

        $this->Properties = array_values(is_null($allowedProperties)
            ? $names
            : array_intersect($names, array_merge(
                $allowedProperties,
                Reflect::getNames(array_filter(
                    $props,
                    function (ReflectionProperty $prop) { return $prop->isPublic(); }
                ))
        )));

        // 2. Resolve "magic" property methods, e.g. _get<Property>()
        $methods = Reflect::getNames(array_values(
            array_filter(
                $class->getMethods($methodFilter),
                function (ReflectionMethod $method) use ($re) { return !$method->isStatic() && preg_match($re, $method->name); }
            )
        ));

        $this->Methods = array_combine(
            array_map(function ($name) use ($re) { preg_match($re, $name, $m); return $m[1]; }, $methods),
            $methods
        );

        // 3. Create normalised property name maps
        if ($this->Normaliser)
        {
            $this->PropertyMap = Convert::listToMap($this->Properties, $this->Normaliser);
            $this->MethodMap   = Convert::listToMap(array_keys($this->Methods), $this->Normaliser);
        }
    }

    public function getClosure(string $name): Closure
    {
        $normalised = ($this->Normaliser
            ? ($this->Normaliser)($name)
            : $name);

        $closure = null;

        // If the [normalised] property name:
        // 1. matches a [normalised] "magic" property method: return a closure
        //    that invokes it
        // 2. matches a [normalised] declared property: return a closure that
        //    assigns, returns, checks or unsets it directly
        // 3. doesn't match an accessible property or method: return a closure
        //    that invokes an IExtensible method if supported
        if ($method = $this->Methods[$this->MethodMap[$normalised] ?? $name] ?? null)
        {
            $closure = function (...$params) use ($method)
            {
                return $this->$method(...$params);
            };
        }
        elseif (in_array($property = $this->PropertyMap[$normalised] ?? $name, $this->Properties))
        {
            switch ($this->Action)
            {
                case "set":

                    $closure = function ($value) use ($property) { $this->$property = $value; };

                    break;

                case "get":

                    $closure = function () use ($property) { return $this->$property; };

                    break;

                case "isset":

                    $closure = function () use ($property) { return isset($this->$property); };

                    break;

                case "unset":

                    // Removal of a declared property is unlikely to be the
                    // intended outcome, so assign null instead of unsetting
                    $closure = function () use ($property) { $this->$property = null; };

                    break;
            }
        }
        elseif (is_a($this->Class, IExtensible::class, true))
        {
            $method = $this->Action == "isset" ? "isMetaPropertySet" : $this->Action . "MetaProperty";

            $closure = function (...$params) use ($method, $name)
            {
                return $this->$method($name, ...$params);
            };
        }

        if (!$closure)
        {
            throw new UnexpectedValueException("Unable to resolve '{$this->Action}' for property '$name'");
        }

        return $closure;
    }
}

