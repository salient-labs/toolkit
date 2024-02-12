<?php declare(strict_types=1);

namespace Lkrms\Container\Event;

use Lkrms\Container\ContainerInterface;

/**
 * Dispatched before the global container is set or unset
 *
 * @api
 */
class BeforeGlobalContainerSetEvent extends ContainerEvent
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
