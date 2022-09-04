<?php

declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Container\Container;
use Lkrms\Contract\HasFacade;
use Lkrms\Contract\IFacade;
use RuntimeException;

/**
 * A static interface to an instance of the underlying class
 *
 */
abstract class Facade implements IFacade
{
    /**
     * Get the name of the underlying class
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
        $service = static::getServiceName();

        if (Container::hasGlobalContainer())
        {
            $instance = Container::getGlobalContainer()->get($service, ...func_get_args());
        }
        else
        {
            $instance = new $service(...func_get_args());
        }

        if ($instance instanceof HasFacade)
        {
            $instance->setFacade(static::class);
        }

        return self::$Instances[static::class] = $instance;
    }

    /**
     * @internal
     */
    final public static function isLoaded(): bool
    {
        return isset(self::$Instances[static::class]);
    }

    /**
     * @internal
     */
    final public static function load()
    {
        if (self::$Instances[static::class] ?? null)
        {
            throw new RuntimeException(static::class . " already loaded");
        }

        return self::_load(...func_get_args());
    }

    /**
     * @internal
     */
    final public static function unload(): void
    {
        unset(self::$Instances[static::class]);
    }

    /**
     * Clear the underlying instances of all facades
     */
    final public static function unloadAll(): void
    {
        self::$Instances = [];
    }

    /**
     * @internal
     */
    final public static function getInstance()
    {
        return self::$Instances[static::class] ?? self::_load();
    }

    /**
     * @internal
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        return static::getInstance()->$name(...$arguments);
    }

}
