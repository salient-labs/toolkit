<?php declare(strict_types=1);

namespace Salient\Container\Event;

use Salient\Contract\Container\BeforeGlobalContainerSetEventInterface;
use Salient\Contract\Container\ContainerInterface;

/**
 * Dispatched before the global container is set or unset
 */
class BeforeGlobalContainerSetEvent extends AbstractContainerEvent implements BeforeGlobalContainerSetEventInterface
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
