<?php

declare(strict_types=1);

namespace Lkrms\Mixin;

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
     * If `$array` values remain, they are assigned to public properties.
     *
     * @param array $array
     * @throws ReflectionException
     * @throws UnexpectedValueException Thrown when required values are not
     * provided or the provided values cannot be applied
     */
    public static function From(array $array): self
    {
        $class = new ReflectionClass(self::class);

        if ($constructor = $class->getConstructor())
        {
            $args = [];

            foreach ($constructor->getParameters() as $param)
            {
                if (array_key_exists($param->name, $array))
                {
                    $args[] = $array[$param->name];
                    unset($array[$param->name]);
                }
                elseif ($param->isOptional())
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

        foreach ($array as $name => $value)
        {
            if ($class->hasProperty($name))
            {
                $property = $class->getProperty($name);

                if (!$property->isStatic() && $property->isPublic())
                {
                    $property->setValue($obj, $value);

                    continue;
                }
            }

            throw new UnexpectedValueException("No public instance property with name '$name' in {$class->name}");
        }

        return $obj;
    }
}

