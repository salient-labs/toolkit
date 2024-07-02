<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * @api
 */
interface HasContainer
{
    /**
     * Get the object's service container
     */
    public function getContainer(): ContainerInterface;
}
