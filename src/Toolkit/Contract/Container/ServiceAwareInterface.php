<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * For classes that need to know when they are used to resolve a service from a
 * container
 *
 * @api
 */
interface ServiceAwareInterface
{
    /**
     * Called when the instance is used to resolve a service from a container
     *
     * If the instance also implements {@see ContainerAwareInterface},
     * {@see ContainerAwareInterface::setContainer()} is called first.
     *
     * @param class-string $service
     */
    public function setService(string $service): void;

    /**
     * Get the last service resolved with the instance
     *
     * If {@see ServiceAwareInterface::setService()} has not been called, the
     * instance should return its own class name.
     *
     * @return class-string
     */
    public function getService(): string;
}
