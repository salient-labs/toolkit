<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Provides services that can be bound to a container
 *
 * Implement {@see SingletonInterface} if a shared instance should be created
 * once per container, {@see ServiceSingletonInterface} if shared instances
 * should be created once per service, or {@see HasServices} if instances of the
 * class should not be shared.
 *
 * If {@see SingletonInterface} and {@see ServiceSingletonInterface} are both
 * implemented, shared instances are created once per service, and an additional
 * shared instance is created to satisfy requests for the class itself.
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
