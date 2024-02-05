<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Implemented by classes that need to know when they are instantiated by a
 * container
 *
 * @api
 */
interface ContainerAwareInterface
{
    /**
     * Called after the instance is created by a container
     */
    public function setContainer(ContainerInterface $container): void;
}
