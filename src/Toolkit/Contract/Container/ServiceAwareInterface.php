<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * @api
 */
interface ServiceAwareInterface
{
    /**
     * Set the service resolved to the instance by the container
     *
     * Called every time the instance is used to resolve a service from the
     * container.
     *
     * {@see ContainerAwareInterface::setContainer()} is called first when the
     * container creates the instance.
     *
     * @param class-string $service
     */
    public function setService(string $service): void;

    /**
     * Get the service resolved to the instance by the container
     *
     * @return class-string
     */
    public function getService(): string;
}
