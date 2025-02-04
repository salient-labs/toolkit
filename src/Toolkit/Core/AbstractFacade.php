<?php declare(strict_types=1);

namespace Salient\Core;

use Salient\Container\Container;
use Salient\Contract\Container\BeforeGlobalContainerSetEventInterface;
use Salient\Contract\Container\ContainerInterface;
use Salient\Contract\Core\Facade\FacadeAwareInterface;
use Salient\Contract\Core\Facade\FacadeInterface;
use Salient\Contract\Core\Instantiable;
use Salient\Contract\Core\Unloadable;
use Salient\Core\Concern\HasUnderlyingService;
use Salient\Core\Facade\App;
use Salient\Core\Facade\Event;
use Salient\Utility\Arr;
use LogicException;

/**
 * @api
 *
 * @template TService of Instantiable
 *
 * @implements FacadeInterface<TService>
 */
abstract class AbstractFacade implements FacadeInterface
{
    /** @use HasUnderlyingService<TService> */
    use HasUnderlyingService;

    /** @var array<class-string<self<*>>,TService> */
    private static array $Instances = [];
    /** @var array<class-string<self<*>>,int> */
    private static array $ListenerIds = [];

    /**
     * Get the facade's underlying class, or an array with the facade's
     * underlying class at index 0 and an inheritor or list thereof at index 1
     *
     * @return class-string<TService>|array{class-string<TService>,class-string<TService>[]|class-string<TService>}
     */
    abstract protected static function getService();

    /**
     * @inheritDoc
     */
    final public static function isLoaded(): bool
    {
        return isset(self::$Instances[static::class]);
    }

    /**
     * @inheritDoc
     */
    final public static function load(?object $instance = null): void
    {
        if (isset(self::$Instances[static::class])) {
            throw new LogicException(sprintf('Already loaded: %s', static::class));
        }
        self::$Instances[static::class] = self::doLoad($instance);
    }

    /**
     * @inheritDoc
     */
    final public static function swap(object $instance): void
    {
        self::doUnload();
        self::$Instances[static::class] = self::doLoad($instance);
    }

    /**
     * @inheritDoc
     */
    final public static function unload(): void
    {
        self::doUnload();
    }

    /**
     * Remove the underlying instances of all facades
     */
    final public static function unloadAll(): void
    {
        foreach (array_keys(self::$Instances) as $class) {
            if ($class !== Event::class) {
                $class::doUnload();
            }
        }
        Event::doUnload();
    }

    /**
     * @inheritDoc
     */
    final public static function getInstance(): object
    {
        $instance = self::$Instances[static::class] ??= self::doLoad();
        return $instance instanceof FacadeAwareInterface
            ? $instance->withoutFacade(static::class, false)
            : $instance;
    }

    /**
     * @inheritDoc
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        $instance = self::$Instances[static::class] ??= self::doLoad();
        return $instance->$name(...$arguments);
    }

    /**
     * @param TService|null $instance
     * @return TService
     */
    private static function doLoad(?object $instance = null): object
    {
        [$name] = self::getNormalisedService();

        if ($instance && !is_a($instance, $name)) {
            throw new LogicException(sprintf(
                '%s does not inherit %s',
                get_class($instance),
                $name,
            ));
        }

        $containerExists = class_exists(Container::class);
        $container = $containerExists && Container::hasGlobalContainer()
            ? Container::getGlobalContainer()
            : null;

        $instance ??= $container
            ? self::getInstanceFromContainer($container)
            : self::createInstance() ?? (
                $containerExists
                    ? self::getInstanceFromContainer(new Container())
                    : null
            );

        if (!$instance) {
            throw new LogicException(sprintf(
                'Service not instantiable: %s::getService()',
                static::class,
            ));
        }

        /** @var EventDispatcher */
        // @phpstan-ignore identical.alwaysFalse
        $dispatcher = static::class === Event::class
            ? $instance
            : Event::getInstance();

        // @phpstan-ignore identical.alwaysFalse
        if (static::class === App::class) {
            /** @var ContainerInterface $instance */
            if ($containerExists && !$container) {
                Container::setGlobalContainer($instance);
            }

            $listenerId = $dispatcher->listen(
                static function (BeforeGlobalContainerSetEventInterface $event): void {
                    if ($container = $event->getContainer()) {
                        App::swap($container);
                    }
                }
            );
        } else {
            if ($container && !$container->hasInstance($name)) {
                $container->instance($name, $instance);
            }

            $listenerId = $dispatcher->listen(
                static function (BeforeGlobalContainerSetEventInterface $event) use ($name, $instance): void {
                    if (($container = $event->getContainer()) && !$container->hasInstance($name)) {
                        $container->instance($name, $instance);
                    }
                }
            );
        }

        self::$ListenerIds[static::class] = $listenerId;

        if ($instance instanceof FacadeAwareInterface) {
            $instance = $instance->withFacade(static::class);
        }

        return $instance;
    }

    /**
     * @return TService|null
     */
    private static function getInstanceFromContainer(ContainerInterface $container): ?object
    {
        [$name, $list] = self::getNormalisedService();

        foreach (Arr::extend([$name], ...$list) as $service) {
            if ($container->has($service)) {
                $instance = $service === $name
                    ? $container->get($name)
                    : $container->getAs($service, $name);
                if (!is_a($instance, $name)) {
                    throw new LogicException(sprintf(
                        '%s does not inherit %s',
                        get_class($instance),
                        $name,
                    ));
                }
                return $instance;
            }
        }

        $service = self::getInstantiableService();
        return $service !== null
            ? $container->getAs($service, $name)
            : null;
    }

    /**
     * @return TService|null
     */
    private static function createInstance(): ?object
    {
        $service = self::getInstantiableService();
        return $service !== null
            ? new $service()
            : null;
    }

    private static function doUnload(): void
    {
        // @phpstan-ignore identical.alwaysFalse
        if (static::class === Event::class) {
            $loaded = array_keys(self::$Instances);
            if ($loaded && $loaded !== [Event::class]) {
                throw new LogicException(sprintf(
                    '%s cannot be unloaded before other facades',
                    Event::class,
                ));
            }
        }

        $id = self::$ListenerIds[static::class] ?? null;
        if ($id !== null) {
            Event::removeListener($id);
            unset(self::$ListenerIds[static::class]);
        }

        $instance = self::$Instances[static::class] ?? null;
        if (!$instance) {
            return;
        }

        // @phpstan-ignore notIdentical.alwaysTrue
        if (static::class !== App::class) {
            $container = class_exists(Container::class)
                && Container::hasGlobalContainer()
                    ? Container::getGlobalContainer()
                    : null;

            if ($container) {
                [$name] = self::getNormalisedService();
                if (
                    $container->hasInstance($name)
                    && $container->get($name) === $instance
                ) {
                    $container->removeInstance($name);
                }
            }
        }

        if ($instance instanceof FacadeAwareInterface) {
            $instance = $instance->withoutFacade(static::class, true);
        }

        if ($instance instanceof Unloadable) {
            $instance->unload();
        }

        unset(self::$Instances[static::class]);
    }
}
