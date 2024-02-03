<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Receives the name of the class or interface it was resolved from
 */
interface ServiceAwareInterface
{
    /**
     * Called immediately after instantiation by a container
     *
     * @param class-string $service The class or interface the container
     * resolved by creating the instance.
     */
    public function setService(string $service): void;
}
