<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use Lkrms\Convert;
use ReflectionClass;
use ReflectionException;
use UnexpectedValueException;

/**
 * Makes constructors with (too) many parameters easier to invoke
 *
 * @package Lkrms
 */
trait TConstructible
{
    /**
     * Create a new class instance from an array
     *
     * The constructor (if any) is invoked with parameters taken from `$array`.
     * If `$array` values remain, they are assigned to public properties. If
     * further values remain and the class implements {@see IExtensible}, they
     * are assigned via {@see IExtensible::SetMetaProperty()}.
     *
     * Array keys, constructor parameters and public property names are
     * normalised for comparison if necessary.
     *
     * @param array $array
     * @return static
     * @throws ReflectionException
     * @throws UnexpectedValueException Thrown when required values are not
     * provided or the provided values cannot be applied
     */
    public static function From(array $array)
    {
        $class = new ReflectionClass(static::class);

        if ($constructor = $class->getConstructor())
        {
            $args = [];

            foreach ($constructor->getParameters() as $param)
            {
                if (!empty($array))
                {
                    // Check for an exact match first
                    if (array_key_exists($param->name, $array))
                    {
                        $args[] = $array[$param->name];
                        unset($array[$param->name]);

                        continue;
                    }

                    // Failing that, normalise $array keys and check for an
                    // inexact match
                    $keys = $keys ?? array_keys($array);
                    $map  = $map ?? array_combine(array_map(function ($k)
                    {
                        return Convert::IdentifierToSnakeCase($k);
                    }, $keys), $keys);

                    if ($key = $map[Convert::IdentifierToSnakeCase($param->name)] ?? null)
                    {
                        $args[] = $array[$key];
                        unset($array[$key]);
                    }
                }

                if ($param->isOptional())
                {
                    $args[] = $param->getDefaultValue();
                }
                elseif ($param->allowsNull())
                {
                    $args[] = null;
                }
                else
                {
                    throw new UnexpectedValueException("No value for required parameter '{$param->name}' in {$class->name}::{$constructor->name}()");
                }
            }

            $obj = $class->newInstanceArgs($args);
        }
        else
        {
            $obj = $class->newInstanceWithoutConstructor();
        }

        if (!empty($array))
        {
            unset($map);

            foreach ($array as $name => $value)
            {
                $propName = $name;

                if (!$class->hasProperty($name))
                {
                    $props = $props ?? array_map(function ($p)
                    {
                        return $p->name;
                    }, $class->getProperties());
                    $map = $map ?? array_combine(array_map(function ($p)
                    {
                        return Convert::IdentifierToSnakeCase($p);
                    }, $props), $props);
                    $propName = $map[Convert::IdentifierToSnakeCase($name)] ?? null;
                }

                if ($propName)
                {
                    $property = $class->getProperty($propName);

                    if (!$property->isStatic() && $property->isPublic())
                    {
                        $property->setValue($obj, $value);

                        continue;
                    }
                }

                if ($obj instanceof IExtensible)
                {
                    $obj->SetMetaProperty($name, $value);

                    continue;
                }

                throw new UnexpectedValueException("No public instance property with name '$name' in {$class->name}");
            }
        }

        return $obj;
    }
}

