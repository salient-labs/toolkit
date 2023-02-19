<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Returns a service container, creating one if necessary
 *
 * @template T of IContainer
 */
interface ReturnsContainer
{
    /**
     * Identical to container()
     *
     * Provided for convenience and consistency.
     *
     * See {@see ReturnsContainer::container()} for more information.
     *
     * @return T
     */
    public function app(): IContainer;

    /**
     * Get the object's service container
     *
     * Objects typically return the container that created them, but if the
     * object was instantiated directly, or didn't receive a container via
     * dependency injection or {@see ReceivesContainer::setContainer()}, it
     * should either:
     * - return {@see \Lkrms\Container\Container::getGlobalContainer()}, or
     * - throw a `RuntimeException`
     *
     * Identical to {@see ReturnsContainer::app()}.
     *
     * @return T
     */
    public function container(): IContainer;
}
