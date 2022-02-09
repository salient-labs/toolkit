<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * A basic implementation of __get
 *
 * Override {@see TGettable::_GetGettable()} to limit access to `protected`
 * variables via `__get`.
 *
 * The default is to allow `__get` for all properties.
 *
 * If `_Get<Property>()` is defined, `__get` will use its return value instead
 * of returning the value of `<Property>`. The existence of `_Get<Property>()`
 * in the exhibiting class implies that `<Property>` is gettable, regardless of
 * {@see TGettable::_GetGettable()}'s return value.
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

    // Populates $GettableMethods, $GettableProperties and their TSettable
    // counterparts
    private function ResolveGettable(string $regex, ?array $allowed, ?array & $properties, ?array & $propertyMethods)
    {
        $getName = function ($reflection) { return $reflection->name; };
        $class   = new ReflectionClass(static::class);

        // Exclude static properties
        $props = $class->getProperties(ReflectionProperty::IS_PROTECTED);
        $props = array_filter($props, function (ReflectionProperty $prop) { return !$prop->isStatic(); });
        $props = array_map($getName, $props);

        if (is_null($allowed))
        {
            $properties = $props;
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
        $methods = array_map($getName, $methods);

        $propertyMethods = array_combine(
            array_map(function ($name) use ($regex) { preg_match($regex, $name, $m); return $m[1]; }, $methods),
            $methods
        );
    }

    final public function __get(string $name)
    {
        $c = static::class;

        if (!array_key_exists($c, self::$GettableProperties))
        {
            $this->ResolveGettable("/^_[gG]et(.+)/", $this->_GetGettable(), self::$GettableProperties[$c], self::$GettableMethods[$c]);
        }

        if ($method = self::$GettableMethods[$c][$name] ?? null)
        {
            return $this->$method();
        }
        elseif (in_array($name, self::$GettableProperties[$c]))
        {
            return $this->$name;
        }
        elseif ($this instanceof IExtensible)
        {
            return $this->GetMetaProperty($name);
        }

        throw new UnexpectedValueException("Cannot access property '$name'");
    }
}

