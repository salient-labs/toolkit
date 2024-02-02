<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Container\Contract\ContainerInterface;

/**
 * Provides services that can be bound to a container
 *
 * Implement {@see IServiceSingleton} if a shared instance should be created
 * once per container, {@see IServiceShared} if shared instances should be
 * created once per service, or {@see IService} if instances of the class should
 * not be shared.
 *
 * If {@see IServiceSingleton} and {@see IServiceShared} are both implemented,
 * shared instances are created once per service, and an additional shared
 * instance is created to satisfy requests for the class itself.
 */
interface IService
{
    /**
     * Get a list of services provided by the class
     *
     * @return class-string[]
     */
    public static function getServices(): array;

    /**
     * Get a dependency substitution map for the class
     *
     * Return an array that maps class or interface names to compatible
     * replacements. Substitutions are applied:
     *
     * - when resolving the class's dependencies, and
     * - when using {@see ContainerInterface::inContextOf()} to work with a
     *   container in the context of the class
     *
     * @return array<class-string,class-string>
     */
    public static function getContextualBindings(): array;
}
