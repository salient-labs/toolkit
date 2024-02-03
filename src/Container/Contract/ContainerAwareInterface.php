<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Receives the container that created it
 */
interface ContainerAwareInterface
{
    /**
     * Called immediately after instantiation by a container
     */
    public function setContainer(ContainerInterface $container): void;
}
