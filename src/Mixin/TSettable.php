<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use Lkrms\Convert;
use ReflectionMethod;
use UnexpectedValueException;

/**
 * A basic implementation of __set and __unset
 *
 * Override {@see TSettable::_GetSettable()} to allow access to `protected`
 * variables via `__set` and `__unset`.
 *
 * The default is to deny `__set` and `__unset` for all properties.
 *
 * - If `_Set<Property>($value)` is defined, it will be called instead of
 *   assigning `$value` to `<Property>`.
 * - If `_Set<Property>` has a second parameter, `_Set<Property>(null, true)`
 *   will be called to unset `<Property>`, otherwise `null` will be assigned.
 * - The existence of `_Set<Property>()` implies that `<Property>` is settable,
 *   regardless of {@see TSettable::_GetSettable()}'s return value.
 *
 * @package Lkrms
 */
trait TSettable
{
    use TGettable;

    /**
     * Return a list of settable `protected` properties, or `null` to allow all
     *
     * @return null|string[]
     */
    protected function _GetSettable(): ?array
    {
        return [];
    }

    private static $SettableProperties = [];

    private static $SettableMethods = [];

    private static $UnsettableMethods = [];

    private static $SettablePropertyMap = [];

    private static $SettableMethodMap = [];

    private function SetProperty(string $name, $value, bool $unset = false)
    {
        $c = static::class;

        if (!array_key_exists($c, self::$SettableProperties))
        {
            $this->ResolveGettable("/^_[sS]et(.+)/", $this->_GetSettable(),
                function (ReflectionMethod $m) { return ($p = $m->getParameters()[1] ?? null) ? $p->allowsNull() : false; },
                self::$SettableProperties[$c], self::$SettableMethods[$c], self::$UnsettableMethods[$c],
                self::$SettablePropertyMap[$c], self::$SettableMethodMap[$c],
                self::$GettableIsNormalised[$c]);
        }

        $normalised = self::$GettableIsNormalised[$c] ? Convert::IdentifierToSnakeCase($name) : $name;
        $property   = self::$SettablePropertyMap[$c][$normalised] ?? $name;
        $methodKey  = self::$SettableMethodMap[$c][$normalised] ?? $name;
        $methods    = $unset ? self::$UnsettableMethods[$c] : self::$SettableMethods[$c];

        if ($method = $methods[$methodKey] ?? null)
        {
            if (!$unset)
            {
                $this->$method($value);
            }
            else
            {
                $this->$method(null, true);
            }
        }
        elseif (in_array($property, self::$SettableProperties[$c]))
        {
            if (!$unset)
            {
                $this->$property = $value;
            }
            else
            {
                $this->$property = null;
            }
        }
        elseif ($this instanceof IExtensible)
        {
            if (!$unset)
            {
                $this->SetMetaProperty($name, $value);
            }
            else
            {
                $this->UnsetMetaProperty($name);
            }
        }
        else
        {
            throw new UnexpectedValueException(!$unset
                ? "Cannot set property '$name'"
                : "Cannot unset property '$name'");
        }
    }

    final public function __set(string $name, $value): void
    {
        $this->SetProperty($name, $value);
    }

    final public function __unset(string $name): void
    {
        $this->SetProperty($name, null, true);
    }

}

