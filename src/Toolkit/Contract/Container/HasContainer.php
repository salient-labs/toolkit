<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * @template TContainer of ContainerInterface
 */
interface HasContainer
{
    /**
     * Get the object's service container
     *
     * @return TContainer
     */
    public function getContainer(): ContainerInterface;
}
