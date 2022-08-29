<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Container\Container;

/**
 * Instances are bound to the container that created them
 *
 */
interface IHasContainer
{
    /**
     * Get the container that created this instance
     *
     * If the instance wasn't created by a container, the current global
     * container will be returned instead.
     *
     * @see Container::getGlobal()
     */
    public function container(): Container;

    /**
     * Bind the instance to the container that created it
     *
     * @throws \RuntimeException if the instance is already bound to a container
     * @return $this
     */
    public function setContainer(Container $container);

    /**
     * Return true if the instance is bound to a container
     *
     */
    public function isContainerSet(): bool;

}
