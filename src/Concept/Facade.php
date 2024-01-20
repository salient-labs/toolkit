<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Container\Event\GlobalContainerSetEvent;
use Lkrms\Container\Container;
use Lkrms\Contract\IFacade;
use Lkrms\Contract\ReceivesFacade;
use Lkrms\Contract\Unloadable;
use Lkrms\Facade\Event;
use Lkrms\Support\EventDispatcher;
use LogicException;

/**
 * Base class for facades
 *
 * @template TClass of object
 * @implements IFacade<TClass>
 */
abstract class Facade implements IFacade
{
    /**
     * Get the name of the underlying class
     *
     * @return class-string<TClass>
     */
    abstract protected static function getServiceName(): string;

    /**
     * @var array<string,object>
     */
    private static $Instances = [];

    /**
     * @var array<string,int>
     */
    private static $ListenerIds = [];

    /**
     * @return TClass
     */
    private static function _load()
    {
        $service = static::getServiceName();

        $container = Container::maybeGetGlobalContainer();
        if ($container) {
            $instance = $container
                ->singletonIf($service)
                ->get($service, func_get_args());
        } else {
            $instance = new $service(...func_get_args());
        }

        if ($instance instanceof ReceivesFacade) {
            $instance->setFacade(static::class);
        }

        $dispatcher = $instance instanceof EventDispatcher
            ? $instance
            : Event::getInstance();
        $id = $dispatcher->listen(
            function (GlobalContainerSetEvent $event) use ($service, $instance): void {
                $container = $event->container();
                if ($container) {
                    $container->instanceIf($service, $instance);
                }
            }
        );
        self::$ListenerIds[static::class] = $id;

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
     * @return TClass
     */
    final public static function load()
    {
        if (isset(self::$Instances[static::class])) {
            throw new LogicException(static::class . ' already loaded');
        }

        return self::_load(...func_get_args());
    }

    /**
     * @internal
     */
    final public static function unload(): void
    {
        $id = self::$ListenerIds[static::class] ?? null;
        if ($id !== null) {
            Event::removeListener($id);
            unset(self::$ListenerIds[static::class]);
        }

        $container = Container::maybeGetGlobalContainer();
        if ($container) {
            $container->unbind(static::getServiceName());
        }

        $instance = self::$Instances[static::class] ?? null;
        if ($instance) {
            if ($instance instanceof Unloadable) {
                $instance->unload();
            }
            unset(self::$Instances[static::class]);
        }
    }

    /**
     * Clear the underlying instances of all facades
     */
    final public static function unloadAll(): void
    {
        foreach (array_keys(self::$Instances) as $class) {
            $class::unload();
        }
    }

    /**
     * @internal
     * @return TClass
     */
    final public static function getInstance()
    {
        return self::$Instances[static::class] ?? self::_load();
    }

    /**
     * @internal
     * @param mixed[] $arguments
     * @return mixed
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        return (self::$Instances[static::class] ?? self::_load())->$name(...$arguments);
    }
}
