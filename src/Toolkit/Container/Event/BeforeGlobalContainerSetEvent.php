<?php declare(strict_types=1);

namespace Salient\Container\Event;

use Salient\Contract\Container\Event\BeforeGlobalContainerSetEvent as BeforeGlobalContainerSetEventInterface;
use Salient\Contract\Container\ContainerInterface;

/**
 * @internal
 */
class BeforeGlobalContainerSetEvent extends ContainerEvent implements BeforeGlobalContainerSetEventInterface
{
    protected ?ContainerInterface $Container;

    public function __construct(?ContainerInterface $container)
    {
        $this->Container = $container;
    }

    /**
     * @inheritDoc
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->Container;
    }
}
