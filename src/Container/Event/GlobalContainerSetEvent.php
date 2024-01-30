<?php declare(strict_types=1);

namespace Lkrms\Container\Event;

use Lkrms\Container\Contract\ContainerInterface;

/**
 * Dispatched when the global container is set or unset
 */
class GlobalContainerSetEvent extends ContainerEvent
{
    protected ?ContainerInterface $Container;

    public function __construct(?ContainerInterface $container)
    {
        $this->Container = $container;
    }

    public function container(): ?ContainerInterface
    {
        return $this->Container;
    }
}
