<?php declare(strict_types=1);

namespace Salient\Core;

use Lkrms\Concern\ResolvesServiceLists;
use Lkrms\Container\Event\BeforeGlobalContainerSetEvent;
use Lkrms\Container\Container;
use Lkrms\Container\ContainerInterface;
use Lkrms\Facade\App;
use Lkrms\Facade\Event;
use Lkrms\Support\EventDispatcher;
use Lkrms\Utility\Get;
use Salient\Core\Contract\FacadeAwareInterface;
use Salient\Core\Contract\FacadeInterface;
use Salient\Core\Contract\Unloadable;
use LogicException;

/**
 * Base class for facades
 *
 * @api
 *
 * @template TService of object
 *
 * @implements FacadeInterface<TService>
 */
abstract class AbstractFacade implements FacadeInterface
{
    /** @use ResolvesServiceLists<TService> */
    use ResolvesServiceLists;

    /**
     * Get the facade's underlying class, or an array that maps its underlying
     * class to compatible implementations
     *
     * At least one of the values returned should be an instantiable class that
     * is guaranteed to exist.
     *
     * @return class-string<TService>|array<class-string<TService>,class-string<TService>|array<class-string<TService>>>
     */
    abstract protected static function getService();

    /**
     * @var array<class-string<static>,TService>
     */
    private static array $Instances = [];

    /**
     * @var array<class-string<static>,int>
     */
    private static array $ListenerIds = [];

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
        self::unload();
        self::$Instances[static::class] = self::doLoad($instance);
    }

    /**
     * Remove the underlying instances of all facades
     */
    final public static function unloadAll(): void
    {
        foreach (array_keys(self::$Instances) as $class) {
            // @phpstan-ignore-next-line
            if ($class === Event::class) {
                continue;
            }
            $class::unload();
        }
        Event::unload();
    }

    /**
     * @inheritDoc
     */
    final public static function unload(): void
    {
        // @phpstan-ignore-next-line
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

        if (static::class !== App::class) {
            $container = Container::maybeGetGlobalContainer();
            if ($container) {
                $serviceName = self::getServiceName();
                if (
                    $container->hasInstance($serviceName) &&
                    $container->get($serviceName) === $instance
                ) {
                    $container->unbindInstance($serviceName);
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

    /**
     * @inheritDoc
     */
    final public static function getInstance(): object
    {
        $instance = self::$Instances[static::class]
            ??= self::doLoad();

        if ($instance instanceof FacadeAwareInterface) {
            return $instance->withoutFacade(static::class, false);
        }

        return $instance;
    }

    /**
     * @param mixed[] $arguments
     * @return mixed
     */
    final public static function __callStatic(string $name, array $arguments)
    {
        return (self::$Instances[static::class]
            ??= self::doLoad())->$name(...$arguments);
    }

    /**
     * @param TService|null $instance
     * @return TService
     */
    private static function doLoad(?object $instance = null): object
    {
        $serviceName = self::getServiceName();

        if ($instance !== null && (
            !is_object($instance) || !is_a($instance, $serviceName)
        )) {
            throw new LogicException(sprintf(
                '%s does not inherit %s',
                Get::type($instance),
                $serviceName,
            ));
        }

        $container = Container::maybeGetGlobalContainer();

        $instance ??= $container
            ? self::getInstanceFromContainer($container, $serviceName)
            : self::createInstance();

        $dispatcher =
            // @phpstan-ignore-next-line
            $instance instanceof EventDispatcher &&
            // @phpstan-ignore-next-line
            static::class === Event::class
                ? $instance
                : Event::getInstance();

        if (
            // @phpstan-ignore-next-line
            $instance instanceof ContainerInterface &&
            // @phpstan-ignore-next-line
            static::class === App::class
        ) {
            if (!$container) {
                Container::setGlobalContainer($instance);
            }

            $listenerId = $dispatcher->listen(
                static function (BeforeGlobalContainerSetEvent $event): void {
                    if ($container = $event->container()) {
                        App::swap($container);
                    }
                }
            );
        } else {
            if ($container) {
                $container->instanceIf($serviceName, $instance);
            }

            $listenerId = $dispatcher->listen(
                static function (BeforeGlobalContainerSetEvent $event) use ($serviceName, $instance): void {
                    if ($container = $event->container()) {
                        $container->instanceIf($serviceName, $instance);
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
     * @return TService
     */
    private static function getInstanceFromContainer(
        ContainerInterface $container,
        string $serviceName
    ): object {
        // If one of the services returned by the facade has been bound to the
        // container, resolve it to an instance
        foreach (self::getServiceList() as $service) {
            if ($container->has($service)) {
                $instance = $container->getAs($service, $serviceName);
                if (!is_a($instance, $serviceName)) {
                    throw new LogicException(sprintf(
                        '%s does not inherit %s: %s::getService()',
                        get_class($instance),
                        $serviceName,
                        static::class,
                    ));
                }
                return $instance;
            }
        }

        // Otherwise, use the container to resolve the first instantiable class
        $service = self::getInstantiableService();
        if ($service !== null) {
            return $container->getAs($service, $serviceName);
        }

        throw new LogicException(sprintf(
            'Service not bound to container: %s::getService()',
            static::class,
        ));
    }

    /**
     * @return TService
     */
    private static function createInstance(): object
    {
        // Create an instance of the first instantiable class
        $service = self::getInstantiableService();
        if ($service !== null) {
            return new $service();
        }

        throw new LogicException(sprintf(
            'Service not instantiable: %s::getService()',
            static::class,
        ));
    }
}
