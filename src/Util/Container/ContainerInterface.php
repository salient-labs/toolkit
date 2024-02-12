<?php declare(strict_types=1);

namespace Lkrms\Container;

use Lkrms\Container\Contract\ContainerAwareInterface;
use Lkrms\Container\Contract\HasBindings;
use Lkrms\Container\Contract\HasContextualBindings;
use Lkrms\Container\Contract\HasServices;
use Lkrms\Container\Contract\ServiceAwareInterface;
use Lkrms\Container\Contract\SingletonInterface;
use Lkrms\Container\Exception\ContainerUnusableArgumentsException;
use Lkrms\Contract\Unloadable;
use Psr\Container\ContainerInterface as PsrContainerInterface;

/**
 * A service container with contextual bindings
 *
 * If a service resolves to a new instance of a class that implements
 * `ContainerAwareInterface`, the container is passed to its
 * {@see ContainerAwareInterface::setContainer()} method.
 *
 * Then, if the resolved instance implements {@see ServiceAwareInterface}, its
 * {@see ServiceAwareInterface::setService()} method is called.
 *
 * A service provider registered via {@see provider()} or {@see providers()} may
 * also implement any combination of the following interfaces:
 *
 * - {@see SingletonInterface} to be instantiated once per container
 * - {@see HasServices} to specify which of its interfaces are services that can
 *   be registered with the container
 * - {@see HasBindings} to register its own bindings with the container
 * - {@see HasContextualBindings} to register container bindings that only apply
 *   when resolving its dependencies
 *
 * {@see SingletonInterface} is ignored if a service lifetime other than
 * {@see ServiceLifetime::INHERIT} is given when the service provider is
 * registered.
 *
 * @api
 */
interface ContainerInterface extends PsrContainerInterface, Unloadable
{
    /**
     * Creates a new service container object
     */
    public function __construct();

    /**
     * True if the global container is set
     */
    public static function hasGlobalContainer(): bool;

    /**
     * Get the global container, creating it if necessary
     */
    public static function getGlobalContainer(): ContainerInterface;

    /**
     * Set or unset the global container
     */
    public static function setGlobalContainer(?ContainerInterface $container): void;

    /**
     * Apply the contextual bindings of a service to a copy of the container
     *
     * @param class-string $id
     * @return static
     */
    public function inContextOf(string $id): self;

    /**
     * Resolve a service from the container
     *
     * Values in `$args` are matched with service dependencies using one or more
     * of the following strategies:
     *
     * - match objects to parameters by hinted type
     * - match other values to parameters by name
     * - match remaining values by type, then position
     *
     * @template T of object
     *
     * @param class-string<T> $id
     * @param mixed[] $args
     * @return T
     * @throws ContainerUnusableArgumentsException if `$args` are given and
     * `$id` resolves to a shared instance.
     */
    public function get(string $id, array $args = []): object;

    /**
     * Resolve a partially-resolved service from the container
     *
     * This method resolves `$id` normally but passes `$service` to
     * {@see ServiceAwareInterface::setService()} instead of `$id`.
     *
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<T> $id
     * @param class-string<TService> $service
     * @param mixed[] $args
     * @return T
     * @throws ContainerUnusableArgumentsException if `$args` are given and
     * `$id` resolves to a shared instance.
     */
    public function getAs(string $id, string $service, array $args = []): object;

    /**
     * Resolve a service from the container to a concrete class name
     *
     * @template T of object
     *
     * @param class-string<T> $id
     * @return class-string<T>
     */
    public function getName(string $id): string;

    /**
     * True if a service is bound to the container
     *
     * @param class-string $id
     */
    public function has(string $id): bool;

    /**
     * True if a service provider is registered with the container
     *
     * @param class-string $id
     */
    public function hasProvider(string $id): bool;

    /**
     * True if a service resolves to a shared instance
     *
     * @param class-string $id
     */
    public function hasInstance(string $id): bool;

    /**
     * Register a binding with the container
     *
     * Subsequent requests for `$id` resolve to `$class`.
     *
     * If `$class` is `null`, `$id` resolves to itself.
     *
     * `$args` are merged with `$args` passed to {@see get()} or {@see getAs()}
     * when `$id` resolves to a new instance of `$class`.
     *
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param class-string<T>|null $class
     * @param mixed[] $args
     * @return $this
     */
    public function bind(
        string $id,
        ?string $class = null,
        array $args = []
    ): self;

    /**
     * Register a binding with the container if it isn't already registered
     *
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param class-string<T>|null $class
     * @param mixed[] $args
     * @return $this
     */
    public function bindIf(
        string $id,
        ?string $class = null,
        array $args = []
    ): self;

    /**
     * Register a shared binding with the container
     *
     * Subsequent requests for `$id` resolve to the instance of `$class` created
     * when `$id` is first requested.
     *
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param class-string<T>|null $class
     * @param mixed[] $args
     * @return $this
     */
    public function singleton(
        string $id,
        ?string $class = null,
        array $args = []
    ): self;

    /**
     * Register a shared binding with the container if it isn't already
     * registered
     *
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param class-string<T>|null $class
     * @param mixed[] $args
     * @return $this
     */
    public function singletonIf(
        string $id,
        ?string $class = null,
        array $args = []
    ): self;

    /**
     * Register a contextual binding with the container
     *
     * Subsequent requests from `$context` for `$dependency` resolve to
     * `$value`.
     *
     * If `$dependency` starts with `'$'`, it is matched with dependencies of
     * `$context` by constructor parameter name, otherwise it is matched by
     * type.
     *
     * If `$value` is a callback, its return value is used.
     *
     * @template TValue
     *
     * @param class-string[]|class-string $context
     * @param class-string<TValue>|string $dependency
     * @param (callable($this): TValue)|class-string<TValue>|TValue $value
     * @return $this
     */
    public function addContextualBinding($context, string $dependency, $value): self;

    /**
     * Register a service provider with the container, optionally specifying
     * which of its services to bind or ignore
     *
     * Unlike {@see bind()} and {@see singleton()}, {@see provider()} loads the
     * given class to check its interfaces and calls static methods like
     * {@see HasContextualBindings::getContextualBindings()}.
     *
     * @param class-string $id
     * @param class-string[]|null $services Services to bind, or `null` to
     * include all services.
     * @param class-string[] $exceptServices Services to ignore.
     * @param ServiceLifetime::* $lifetime
     * @return $this
     */
    public function provider(
        string $id,
        ?array $services = null,
        array $exceptServices = [],
        int $lifetime = ServiceLifetime::INHERIT
    ): self;

    /**
     * Register a service map with the container
     *
     * A service map is an array with entries that map a service (usually an
     * interface) to a service provider (a class that extends or implements the
     * service).
     *
     * Multiple services may be mapped to the same service provider, and service
     * providers may be mapped to themselves. For example, to make `BarClass`
     * discoverable via {@see ContainerInterface::getProviders()} whether it's
     * bound to a service or not:
     *
     * ```php
     * <?php
     * $container->providers([
     *     FooInterface::class => Env::get('foo_provider'),
     *     BarClass::class => BarClass::class,
     * ]);
     * ```
     *
     * @param array<class-string,class-string> $serviceMap
     * @param ServiceLifetime::* $lifetime
     * @return $this
     */
    public function providers(
        array $serviceMap,
        int $lifetime = ServiceLifetime::INHERIT
    ): self;

    /**
     * Get a list of service providers registered with the container
     *
     * @return array<class-string>
     */
    public function getProviders(): array;

    /**
     * Register an object with the container as a shared binding
     *
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param T $instance
     * @return $this
     */
    public function instance(string $id, $instance): self;

    /**
     * Register an object with the container as a shared binding if it isn't
     * already registered
     *
     * @template TService of object
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param T $instance
     * @return $this
     */
    public function instanceIf(string $id, $instance): self;

    /**
     * Remove a binding from the container
     *
     * @param class-string $id
     * @return $this
     */
    public function unbind(string $id): self;
}
