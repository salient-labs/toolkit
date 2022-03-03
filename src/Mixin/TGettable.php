<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use Lkrms\Convert;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * A basic implementation of __get and __isset
 *
 * Override {@see TGettable::_GetGettable()} to limit access to `protected`
 * variables via `__get` and `__isset`.
 *
 * The default is to allow `__get` and `__isset` for all properties.
 *
 * - If `_Get<Property>()` is defined, `__get` will use its return value instead
 *   of returning the value of `<Property>`.
 * - If `_Get<Property>` has a parameter, `_Get<Property>(true)` will be
 *   returned instead of `isset(<Property>)`.
 * - The existence of `_Get<Property>()` implies that `<Property>` is gettable,
 *   regardless of {@see TGettable::_GetGettable()}'s return value.
 *
 * @package Lkrms
 */
trait TGettable
{
    /**
     * Return a list of gettable `protected` properties, or `null` to allow all
     *
     * @return null|string[]
     */
    protected function _GetGettable(): ?array
    {
        return null;
    }

    private static $GettableProperties = [];

    private static $GettableMethods = [];

    private static $IssettableMethods = [];

    private static $GettablePropertyMap = [];

    private static $GettableMethodMap = [];

    private static $GettableIsNormalised = [];

    /**
     * Populate $GettableProperties, $GettableMethods, $IssettableMethods,
     * $GettablePropertyMap, $GettableMethodMap, $GettableIsNormalised and their
     * TSettable counterparts
     *
     * @param string $regex Used to identify `protected` and `public` property
     * getter (or setter) methods.
     * @param null|string[] $allowed The array returned by
     * {@see TGettable::_GetGettable()} (or {@see TSettable::_GetSettable()}).
     * @param callable $methodFilter
     * @param null|string[] $properties
     * @param null|string[] $propertyMethods
     * @param null|string[] $filteredMethods
     * @param null|array<string,string> $propertyMap
     * @param null|array<string,string> $methodMap
     * @param null|bool $normalised
     */
    private function ResolveGettable(string $regex, ?array $allowed, callable $methodFilter,
        ?array & $properties, ?array & $propertyMethods, ?array & $filteredMethods,
        ?array & $propertyMap, ?array & $methodMap, ?bool & $normalised)
    {
        $getName = function ($reflection) { return $reflection->name; };
        $class   = new ReflectionClass(static::class);

        $props = $class->getProperties(ReflectionProperty::IS_PROTECTED | ($this instanceof IResolvable ? ReflectionProperty::IS_PUBLIC : 0));
        $props = array_filter($props, function (ReflectionProperty $prop) { return !$prop->isStatic(); });
        $props = array_map($getName, $props);

        if (is_null($allowed))
        {
            $properties = array_values($props);
        }
        else
        {
            $properties = array_values(array_intersect($allowed, $props));
        }

        $methods = $class->getMethods();
        $methods = array_filter($methods, function (ReflectionMethod $method) use ($regex)
        {
            return !$method->isPrivate() && !$method->isStatic() &&
                preg_match($regex, $method->name) &&
                !preg_match("/^_Get[GS]ettable\$/", $method->name);
        });
        $filtered = array_filter($methods, $methodFilter);

        foreach ([[$methods, &$propertyMethods], [$filtered, &$filteredMethods]] as list ($list, &$prop))
        {
            $list = array_map($getName, $list);
            $prop = array_combine(
                array_map(function ($name) use ($regex) { preg_match($regex, $name, $m); return $m[1]; }, $list),
                $list
            );
        }

        unset($prop);

        // If the class implements IResolvable, create maps from normalised to
        // actual property names
        if ($normalised === true || $this instanceof IResolvable)
        {
            $mapFrom = function ($name) { return Convert::IdentifierToSnakeCase($name); };
            $map     = function ($arr) use ($mapFrom) { return array_combine(array_map($mapFrom, $arr), $arr); };

            $propertyMap = $map($properties);
            $methodMap   = $map($propertyMethods);
            $normalised  = true;
        }
        else
        {
            $normalised = false;
        }
    }

    private function GetProperty(string $name, bool $isset = false)
    {
        $c = static::class;

        if (!array_key_exists($c, self::$GettableProperties))
        {
            $this->ResolveGettable("/^_[gG]et(.+)/", $this->_GetGettable(),
                function (ReflectionMethod $m) { return ($p = $m->getParameters()[0] ?? null) ? $p->allowsNull() : false; },
                self::$GettableProperties[$c], self::$GettableMethods[$c], self::$IssettableMethods[$c],
                self::$GettablePropertyMap[$c], self::$GettableMethodMap[$c],
                self::$GettableIsNormalised[$c]);
        }

        $normalised = self::$GettableIsNormalised[$c] ? Convert::IdentifierToSnakeCase($name) : $name;
        $property   = self::$GettablePropertyMap[$c][$normalised] ?? $name;
        $methodKey  = self::$GettableMethodMap[$c][$normalised] ?? $name;
        $methods    = $isset ? self::$IssettableMethods[$c] : self::$GettableMethods[$c];

        if ($method = $methods[$methodKey] ?? null)
        {
            if (!$isset)
            {
                return $this->$method();
            }
            else
            {
                return (bool)$this->$method(true);
            }
        }
        elseif (in_array($property, self::$GettableProperties[$c]))
        {
            if (!$isset)
            {
                return $this->$property;
            }
            else
            {
                return isset($this->$property);
            }
        }
        elseif ($this instanceof IExtensible)
        {
            if (!$isset)
            {
                return $this->GetMetaProperty($name);
            }
            else
            {
                return $this->IsMetaPropertySet($name);
            }
        }

        throw new UnexpectedValueException(!$isset
            ? "Cannot access property '$name'"
            : "Cannot check if property '$name' is set");
    }

    final public function __get(string $name): mixed
    {
        return $this->GetProperty($name);
    }

    final public function __isset(string $name): bool
    {
        return $this->GetProperty($name, true);
    }
}

