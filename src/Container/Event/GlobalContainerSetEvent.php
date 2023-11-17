<?php declare(strict_types=1);

namespace Lkrms\Container\Event;

use Lkrms\Contract\IContainer;

/**
 * Dispatched when the global container is set or unset
 */
class GlobalContainerSetEvent extends ContainerEvent
{
    protected ?IContainer $Container;

    public function __construct(?IContainer $container)
    {
        $this->Container = $container;
    }

    public function container(): ?IContainer
    {
        return $this->Container;
    }
}
