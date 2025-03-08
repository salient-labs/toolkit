<?php declare(strict_types=1);

namespace Salient\Contract\Container\Event;

use Salient\Contract\Container\ContainerInterface;

/**
 * @api
 */
interface BeforeGlobalContainerSetEvent
{
    /**
     * Get the container that will be set, or null if it will be unset
     */
    public function getContainer(): ?ContainerInterface;
}
