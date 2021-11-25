<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use ReflectionClass;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * A basic implementation of __get
 *
 * Override {@see TGettable::_GetGettableProperties()} to limit access to
 * private and protected variables.
 *
 * If `_Get<Property>()` is found in the exhibiting class, its return value will
 * be used instead of the property value.
 *
 * @package Lkrms
 */
trait TGettable
{
    /**
     * Return a list of allowed property names, or null to allow all
     *
     * @return null|string[]
     */
    protected function _GetGettableProperties(): ?array
    {
        return null;
    }

    private static $GettableProperties = [];

    private static $GettablePropertyMethods = [];

    final public function __get(string $name)
    {
        $c = static::class;

        if (!array_key_exists($c, self::$GettableProperties))
        {
            $class = new ReflectionClass($c);
            self::$GettableProperties[$c]      = $this->_GetGettableProperties();
            self::$GettablePropertyMethods[$c] = [];

            if (is_null(self::$GettableProperties[$c]))
            {
                self::$GettableProperties[$c] = array_map(
                    function ($property)
                    {
                        return $property->name;
                    },
                    $class->getProperties(
                        ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED
                    )
                );
            }

            self::$GettableProperties[$c] = array_filter(
                self::$GettableProperties[$c],
                function ($p)
                {
                    return !in_array($p, [
                        "GettableProperties",
                        "GettablePropertyMethods",
                        "SettableProperties",
                        "SettablePropertyMethods",
                    ]);
                }
            );

            foreach (self::$GettableProperties[$c] as $p)
            {
                $m = "_Get" . $p;

                if ($class->hasMethod($m))
                {
                    self::$GettablePropertyMethods[$c][$p] = $m;
                }
            }
        }

        if (!in_array($name, self::$GettableProperties[$c]))
        {
            throw new UnexpectedValueException("Cannot access property '$name'");
        }

        return ($m = self::$GettablePropertyMethods[$c][$name] ?? null) ? $this->$m() : $this->$name;
    }
}

