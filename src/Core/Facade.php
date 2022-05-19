<?php

declare(strict_types=1);

namespace Lkrms\Core;

use Lkrms\Core\Contract\ISingular;
use Lkrms\Ioc\Ioc;
use RuntimeException;

/**
 * Base class for facades
 *
 * @package Lkrms
 */
abstract class Facade implements ISingular
{
    /**
     * Return the name of the class to instantiate behind the facade
     *
     * @return string
     */
    abstract protected static function getServiceName(): string;

    /**
     * @var array<string,object>
     */
    private static $Instances = [];

    /**
     * Return true if the underlying instance has been initialised
     *
     * @return bool
     */
    final public static function isLoaded(): bool
    {
        return isset(self::$Instances[static::class]);
    }

    /**
     * Create, initialise and return the underlying instance
     *
     * @return object
     */
    final public static function load()
    {
        if (self::$Instances[static::class] ?? null)
        {
            throw new RuntimeException(static::class . " already loaded");
        }

        $instance = Ioc::create(static::getServiceName(), func_get_args());
        self::$Instances[static::class] = $instance;
        return $instance;
    }

    /**
     * Return the underlying instance, creating it if necessary
     *
     * @return object
     */
    final public static function getInstance()
    {
        return self::$Instances[static::class] ?? static::load();
    }

    /**
     * Pass static method calls to the underlying instance
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        return static::getInstance()->$name(...$arguments);
    }

}
