<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * @api
 */
interface ContainerAwareInterface
{
    /**
     * Set the container that created the instance
     */
    public function setContainer(ContainerInterface $container): void;
}
