<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * @api
 */
interface BeforeGlobalContainerSetEventInterface
{
    /**
     * Get the container that will be set, or null if it will be unset
     */
    public function getContainer(): ?ContainerInterface;
}
