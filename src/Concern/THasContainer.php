<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;
use RuntimeException;

/**
 * Implements IHasContainer to bind instances to the container that created them
 *
 * @see \Lkrms\Contract\IHasContainer
 */
trait THasContainer
{
    /**
     * @var Container|null
     */
    private $Container;

    public function container(): Container
    {
        return $this->Container ?: Container::getGlobal();
    }

    /**
     * @return $this
     */
    public function setContainer(Container $container)
    {
        if (!is_null($this->Container))
        {
            throw new RuntimeException("Container already set");
        }
        $this->Container = $container;
        return $this;
    }

    public function isContainerSet(): bool
    {
        return !is_null($this->Container);
    }

}
