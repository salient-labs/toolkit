<?php

declare(strict_types=1);

namespace Lkrms\Template;

use Lkrms\Convert;
use Lkrms\Ioc\Ioc;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use UnexpectedValueException;

/**
 * Converts arrays to instances
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
     * are assigned via {@see IExtensible::setMetaProperty()}.
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
    public static function from(array $array)
    {
        /**
         * @todo Reimplement with caching
         */

        $class = new ReflectionClass(Ioc::resolve(static::class));

        $getName = function ($reflection) { return $reflection->name; };
        $mapFrom = function ($name) { return Convert::toSnakeCase($name); };

        // $arrayMap: normalised_name => ORIGINAL_NAME
        $keys     = array_keys($array);
        $arrayMap = array_combine(array_map($mapFrom, $keys), $keys);

        $args = null;

        if ($constructor = $class->getConstructor())
        {
            // $paramMap: originalName => normalised_name
            $params   = $constructor->getParameters();
            $keys     = array_map($getName, $params);
            $paramMap = array_combine($keys, array_map($mapFrom, $keys));

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
        }

        $obj = Ioc::create(static::class, $args);

        if (empty($array))
        {
            return $obj;
        }

        // $propMap: normalised_name => OriginalName
        $props = array_filter(
            $class->getProperties(ReflectionProperty::IS_PUBLIC),
            function ($prop) { return !$prop->isStatic(); }
        );
        $keys    = array_map($getName, $props);
        $propMap = array_combine(array_map($mapFrom, $keys), $keys);

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

    /**
     * Convert a list of arrays to a list of instances
     *
     * Array keys are not preserved.
     *
     * @param array<int,array|static> $arrays Nested arrays are passed to
     * {@see TConstructible::from()}. Instances are added to the list as-is.
     * @return static[]
     */
    public static function listFrom(array $arrays): array
    {
        $list = [];

        foreach ($arrays as $index => $array)
        {
            if ($array instanceof static )
            {
                $list[] = $array;

                continue;
            }
            elseif (!is_array($array))
            {
                throw new UnexpectedValueException("Array expected at index $index");
            }

            $list[] = static::from($array);
        }

        return $list;
    }
}

