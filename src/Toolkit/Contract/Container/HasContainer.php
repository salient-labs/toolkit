<?php declare(strict_types=1);

namespace Salient\Container\Contract;

use Salient\Container\ContainerInterface;

/**
 * @template T of ContainerInterface
 */
interface HasContainer
{
    /**
     * Get the object's service container
     *
     * @return T
     */
    public function app(): ContainerInterface;

    /**
     * Get the object's service container
     *
     * @return T
     */
    public function container(): ContainerInterface;
}
