<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Container\Container;
use Lkrms\Contract\IFacade;
use RuntimeException;

/**
 * Base class for facades
 *
 */
abstract class Facade implements IFacade
{
    /**
     * Return the class or interface to instantiate behind the facade
     *
     * @return string
     */
    abstract protected static function getServiceName(): string;

    /**
     * @var array<string,object>
     */
    private static $Instances = [];

    private static function _load()
    {
        if (is_a($service = static::getServiceName(), Container::class, true))
        {
            $container = $service::getGlobal(...func_get_args());
            if (is_a($container, $service))
            {
                return self::$Instances[static::class] = $container;
            }
            throw new RuntimeException("Global container already exists");
        }
        $container = Container::getGlobal();

        return self::$Instances[static::class] = $container->get($service, ...func_get_args());
    }

    final public static function isLoaded(): bool
    {
        return isset(self::$Instances[static::class]);
    }

    final public static function load()
    {
        if (self::$Instances[static::class] ?? null)
        {
            throw new RuntimeException(static::class . " already loaded");
        }

        return self::_load(...func_get_args());
    }

    final public static function getInstance()
    {
        return self::$Instances[static::class] ?? self::_load();
    }

    /**
     * Pass static method calls to the instance behind the facade
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
