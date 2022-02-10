<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

use Lkrms\Convert;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
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

        $nameCallback      = function ($reflection) { return $reflection->name; };
        $normaliseCallback = function ($name) { return Convert::IdentifierToSnakeCase($name); };

        // $arrayMap: normalised_name => ORIGINAL_NAME
        $keys     = array_keys($array);
        $arrayMap = array_combine(array_map($normaliseCallback, $keys), $keys);

        if ($constructor = $class->getConstructor())
        {
            // $paramMap: originalName => normalised_name
            $params   = $constructor->getParameters();
            $keys     = array_map($nameCallback, $params);
            $paramMap = array_combine($keys, array_map($normaliseCallback, $keys));

            $args = [];

            foreach ($params as $param)
            {
                if (!empty($array))
                {
                    // Try for an exact match
                    if (array_key_exists($param->name, $array))
                    {
                        $key = $param->name;
                    }
                    else
                    {
                        // Settle for a less exact one
                        $key = $arrayMap[$paramMap[$param->name]] ?? null;
                    }

                    if ($key)
                    {
                        $args[] = $array[$key];
                        unset($array[$key], $arrayMap[$paramMap[$param->name]]);

                        continue;
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

        if (empty($array))
        {
            return $obj;
        }

        // $propMap: normalised_name => OriginalName
        $props = array_filter(
            $class->getProperties(ReflectionProperty::IS_PUBLIC),
            function ($prop) { return !$prop->isStatic(); }
        );
        $keys    = array_map($nameCallback, $props);
        $propMap = array_combine(array_map($normaliseCallback, $keys), $keys);

        $props = array_intersect_key($propMap, $arrayMap);

        foreach ($props as $normalised => $prop)
        {
            $obj->$prop = $array[$arrayMap[$normalised]];
            unset($array[$arrayMap[$normalised]], $arrayMap[$normalised]);
        }

        if (empty($array))
        {
            return $obj;
        }

        if ($obj instanceof IExtensible)
        {
            foreach ($array as $name => $value)
            {
                $obj->$name = $value;
            }
        }
        else
        {
            throw new UnexpectedValueException("Not found in {$class->name}: " . implode(", ", $arrayMap));
        }

        return $obj;
    }
}

