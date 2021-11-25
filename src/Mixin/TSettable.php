<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use ReflectionClass;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * A basic implementation of __set
 *
 * Override {@see TSettable::_GetSettableProperties()} to allow access to
 * private and protected variables.
 *
 * If `_Set<Property>($value)` is found in the exhibiting class, it will be
 * called instead of setting the property directly.
 *
 * @package Lkrms
 */
trait TSettable
{
    /**
     * Return a list of allowed property names, or null to allow all
     *
     * @return null|string[]
     */
    protected function _GetSettableProperties(): ?array
    {
        return null;
    }

    private static $SettableProperties = [];

    private static $SettablePropertyMethods = [];

    final public function __set(string $name, $value)
    {
        $c = static::class;

        if (!array_key_exists($c, self::$SettableProperties))
        {
            $auto  = false;
            $class = new ReflectionClass($c);
            self::$SettableProperties[$c]      = $this->_GetSettableProperties();
            self::$SettablePropertyMethods[$c] = [];

            if (is_null(self::$SettableProperties[$c]))
            {
                $auto = true;
                self::$SettableProperties[$c] = array_map(
                    function ($property)
                    {
                        return $property->name;
                    },
                    $class->getProperties(
                        ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED
                    )
                );
            }

            self::$SettableProperties[$c] = array_filter(
                self::$SettableProperties[$c],
                function ($p) use ($auto, $class)
                {
                    if ($auto)
                    {
                        return $class->hasMethod("_Set" . $p);
                    }
                    else
                    {
                        return !in_array($p, [
                            "GettableProperties",
                            "GettablePropertyMethods",
                            "SettableProperties",
                            "SettablePropertyMethods",
                        ]);
                    }
                }
            );

            foreach (self::$SettableProperties[$c] as $p)
            {
                $m = "_Set" . $p;

                if ($auto || $class->hasMethod($m))
                {
                    self::$SettablePropertyMethods[$c][$p] = $m;
                }
            }
        }

        if (!in_array($name, self::$SettableProperties[$c]))
        {
            throw new UnexpectedValueException("Cannot set property '$name'");
        }

        if ($m = self::$SettablePropertyMethods[$c][$name] ?? null)
        {
            $this->$m($value);
        }
        else
        {
            $this->$name = $value;
        }
    }
}

