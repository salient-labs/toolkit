<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Container\Contract\ContainerInterface;

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
