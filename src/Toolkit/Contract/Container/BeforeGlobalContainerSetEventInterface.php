<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * Dispatched before the global container is set or unset
 *
 * @api
 */
interface BeforeGlobalContainerSetEventInterface
{
    /**
     * Get the container that will be set, or null if it will be unset
     */
    public function getContainer(): ?ContainerInterface;
}
