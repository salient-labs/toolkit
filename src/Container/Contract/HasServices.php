<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Implemented by service providers that specify which of their interfaces can
 * be registered with a container
 */
interface HasServices
{
    /**
     * Get a list of services provided by the class
     *
     * @return class-string[]
     */
    public static function getServices(): array;
}
