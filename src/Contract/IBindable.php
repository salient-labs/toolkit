<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Container\Container;

/**
 * Provides services and binds them to containers
 *
 */
interface IBindable extends IBound
{
    /**
     * Create a container binding for the class
     *
     * @param Container $container
     */
    public static function bind(Container $container);

    /**
     * Create container bindings for services provided by the class
     *
     * @param Container $container
     * @param string ...$interfaces Only bind the given interfaces. If no
     * interfaces are specified, every service interface implemented by the
     * class will be bound.
     */
    public static function bindServices(Container $container, string ...$interfaces);

    /**
     * Create container bindings for services provided by the class that
     * aren't in the given exception list
     *
     * @param Container $container
     * @param string ...$interfaces At least one interface to exclude.
     */
    public static function bindServicesExcept(Container $container, string ...$interfaces);

    /**
     * Create container bindings for concrete classes
     */
    public static function bindConcrete(Container $container);

    /**
     * Bind the class to a service container and run the given callback, then
     * restore the container to its original state
     *
     * @param callable $callback `$container` is cloned and passed to
     * `$callback` so bindings applied by the class can be used in asynchronous
     * contexts after the global container has been restored, e.g. in generator
     * functions, which don't run until they are traversed.
     * ```php
     * callback(\Psr\Container\ContainerInterface $container): mixed
     * ```
     * @param Container|null $container If set, use `$container` as the basis
     * for the temporary service container. The default is to use the container
     * returned by {@see IBindable::container()}.
     * @return mixed The callback's return value (if any).
     */
    public function invokeInBoundContainer(callable $callback, Container $container = null);

    /**
     * @return Container
     */
    public function container(): Container;

}
