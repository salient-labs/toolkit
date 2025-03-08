<?php declare(strict_types=1);

namespace Salient\Contract\Container;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use Salient\Contract\Container\Event\BeforeGlobalContainerSetEvent;
use Salient\Contract\Container\Exception\InvalidServiceException;
use Salient\Contract\Container\Exception\ServiceNotFoundException;
use Salient\Contract\Container\Exception\UnusedArgumentsException;
use Salient\Contract\Core\Chainable;
use Salient\Contract\Core\Instantiable;
use Salient\Contract\Core\Unloadable;
use Closure;

/**
 * @api
 */
interface ContainerInterface extends
    PsrContainerInterface,
    Chainable,
    Instantiable,
    Unloadable,
    HasServiceLifetime
{
    /**
     * Creates a new service container
     */
    public function __construct();

    /**
     * Check if the global container is set
     */
    public static function hasGlobalContainer(): bool;

    /**
     * Get the global container, creating it if necessary
     */
    public static function getGlobalContainer(): ContainerInterface;

    /**
     * Set or unset the global container
     *
     * Dispatches {@see BeforeGlobalContainerSetEvent} if the global container
     * will change.
     */
    public static function setGlobalContainer(?ContainerInterface $container): void;

    /**
     * Apply contextual bindings to a copy of the container
     *
     * @param class-string $id
     * @return static
     */
    public function inContextOf(string $id): ContainerInterface;

    /**
     * Resolve a service from the container
     *
     * Values in `$args` are passed to constructor parameters after:
     *
     * 1. matching objects to parameters with compatible type declarations
     * 2. matching keys in `$args` to parameters with the same name
     * 3. matching values to parameters by type and position
     *
     * @template T
     *
     * @param class-string<T> $id
     * @param mixed[] $args
     * @return T&object
     * @throws ServiceNotFoundException if `$id` is not instantiable.
     * @throws UnusedArgumentsException if `$args` are given and `$id` resolves
     * to a shared instance.
     */
    public function get(string $id, array $args = []): object;

    /**
     * Resolve a partially-resolved service from the container
     *
     * `$id` is resolved normally, but `$service` is passed to
     * {@see ServiceAwareInterface::setService()} instead of `$id`.
     *
     * @template TService
     * @template T of TService
     *
     * @param class-string<T> $id
     * @param class-string<TService> $service
     * @param mixed[] $args
     * @return T&object
     * @throws ServiceNotFoundException if `$id` is not instantiable.
     * @throws UnusedArgumentsException if `$args` are given and `$id` resolves
     * to a shared instance.
     */
    public function getAs(string $id, string $service, array $args = []): object;

    /**
     * Resolve a service from the container without returning an instance
     *
     * Returns the class name of the object {@see get()} would return.
     *
     * @template T
     *
     * @param class-string<T> $id
     * @return class-string<T>
     */
    public function getName(string $id): string;

    /**
     * Check if a service is bound to the container
     *
     * @param class-string $id
     */
    public function has(string $id): bool;

    /**
     * Check if a shared service or instance is bound to the container
     *
     * @param class-string $id
     */
    public function hasSingleton(string $id): bool;

    /**
     * Check if a service resolves to a shared instance
     *
     * @param class-string $id
     */
    public function hasInstance(string $id): bool;

    /**
     * Check if a service provider is registered with the container
     *
     * @param class-string $provider
     */
    public function hasProvider(string $provider): bool;

    /**
     * Get a list of service providers registered with the container
     *
     * @return array<class-string>
     */
    public function getProviders(): array;

    /**
     * Bind a service to the container
     *
     * Subsequent requests for `$id` resolve to an instance of `$class`, or
     * `$id` if `$class` is `null`.
     *
     * If `$class` is a closure, it is called every time `$id` is resolved.
     *
     * @template TService
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param (Closure(self): T&object)|class-string<T>|null $class
     * @return $this
     */
    public function bind(string $id, $class = null): ContainerInterface;

    /**
     * Bind a service to the container if it isn't already bound
     *
     * @template TService
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param (Closure(self): T&object)|class-string<T>|null $class
     * @return $this
     */
    public function bindIf(string $id, $class = null): ContainerInterface;

    /**
     * Bind a shared service to the container
     *
     * Subsequent requests for `$id` resolve to the shared instance created when
     * `$id` is first requested.
     *
     * @template TService
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param (Closure(self): T&object)|class-string<T>|null $class
     * @return $this
     */
    public function singleton(string $id, $class = null): ContainerInterface;

    /**
     * Bind a shared service to the container if it isn't already bound
     *
     * @template TService
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param (Closure(self): T&object)|class-string<T>|null $class
     * @return $this
     */
    public function singletonIf(string $id, $class = null): ContainerInterface;

    /**
     * Bind a shared instance to the container
     *
     * @template TService
     * @template T of TService
     *
     * @param class-string<TService> $id
     * @param T&object $instance
     * @return $this
     */
    public function instance(string $id, object $instance): ContainerInterface;

    /**
     * Remove a shared instance from the container
     *
     * @param class-string $id
     * @return $this
     */
    public function removeInstance(string $id): ContainerInterface;

    /**
     * Add a contextual binding to the container
     *
     * Subsequent requests for `$id` from the given contexts resolve to:
     *
     * - the return value of `$class` (if it is a closure)
     * - an instance of `$class` (if it is a string)
     * - `$class` itself (if it is an object), or
     * - an instance of `$id` (if `$class` is `null`)
     *
     * If `$id` starts with `'$'`, it is matched with constructor parameters of
     * the same name, and `$class` cannot be `null`.
     *
     * @template TService
     * @template T of TService
     *
     * @param class-string[]|class-string $context
     * @param class-string<TService>|non-empty-string $id
     * @param (Closure(self): T&object)|class-string<T>|(T&object)|null $class
     * @return $this
     */
    public function addContextualBinding(
        $context,
        string $id,
        $class = null
    ): ContainerInterface;

    /**
     * Register a service provider with the container, optionally specifying
     * which of its services to bind or ignore
     *
     * For performance reasons, classes bound to the container with
     * {@see bind()} or {@see singleton()} are not loaded until they are
     * resolved. Classes registered with {@see provider()} are loaded
     * immediately to check for {@see HasServices}, {@see SingletonInterface}
     * and other implementations.
     *
     * @param class-string $provider
     * @param class-string[]|null $services Services of `$provider` to bind to
     * the container, or `null` to bind every service returned by
     * {@see HasServices::getServices()} (if implemented).
     * @param class-string[] $excludeServices Services of `$provider` to exclude
     * from binding.
     * @param ContainerInterface::* $providerLifetime
     * @return $this
     * @throws InvalidServiceException if `$provider` returns invalid container
     * bindings or doesn't provide one of the given `$services`.
     */
    public function provider(
        string $provider,
        ?array $services = null,
        array $excludeServices = [],
        int $providerLifetime = ContainerInterface::LIFETIME_INHERIT
    ): ContainerInterface;

    /**
     * Register an array that maps services (usually interfaces) to service
     * providers (classes that extend or implement the mapped service)
     *
     * Multiple services may be mapped to the same service provider. Unmapped
     * providers are mapped to themselves.
     *
     * @param array<class-string|int,class-string> $providers
     * @param ContainerInterface::* $providerLifetime
     * @return $this
     */
    public function providers(
        array $providers,
        int $providerLifetime = ContainerInterface::LIFETIME_INHERIT
    ): ContainerInterface;
}
