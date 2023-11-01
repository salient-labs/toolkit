<?php declare(strict_types=1);

namespace Lkrms\Concept;

use Lkrms\Container\Container;
use Lkrms\Contract\IContainer;
use Lkrms\Contract\IFacade;
use Lkrms\Contract\ReceivesFacade;
use Lkrms\Facade\Event;
use Lkrms\Support\EventDispatcher;
use RuntimeException;

/**
 * A static interface to an instance of an underlying class
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

        if ($container = Container::maybeGetGlobalContainer()) {
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
            'container.global.set',
            function (?IContainer $container) use ($service, $instance): void {
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
            throw new RuntimeException(static::class . ' already loaded');
        }

        return self::_load(...func_get_args());
    }

    /**
     * @internal
     */
    final public static function unload(): void
    {
        if ($id = self::$ListenerIds[static::class] ?? null) {
            Event::removeListener($id);
            unset(self::$ListenerIds[static::class]);
        }
        if ($container = Container::maybeGetGlobalContainer()) {
            $container->unbind(static::getServiceName());
        }
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
