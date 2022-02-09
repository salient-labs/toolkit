<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use UnexpectedValueException;

/**
 * A basic implementation of __set
 *
 * Override {@see TSettable::_GetSettable()} to allow access to `protected`
 * variables via `__set`.
 *
 * The default is to deny `__set` for all properties.
 *
 * If `_Set<Property>($value)` is defined, it will be called instead of
 * assigning `$value` to `<Property>`. The existence of `_Set<Property>()` in
 * the exhibiting class implies that `<Property>` is settable, regardless of
 * {@see TSettable::_GetSettable()}'s return value.
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

    final public function __set(string $name, $value)
    {
        $c = static::class;

        if (!array_key_exists($c, self::$SettableProperties))
        {
            $this->ResolveGettable("/^_[sS]et(.+)/", $this->_GetSettable(), self::$SettableProperties[$c], self::$SettableMethods[$c]);
        }

        if ($method = self::$SettableMethods[$c][$name] ?? null)
        {
            $this->$method($value);
        }
        elseif (in_array($name, self::$SettableProperties[$c]))
        {
            $this->$name = $value;
        }
        elseif ($this instanceof IExtensible)
        {
            $this->SetMetaProperty($name, $value);
        }
        else
        {
            throw new UnexpectedValueException("Cannot set property '$name'");
        }
    }
}

