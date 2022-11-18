<?php

declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Provides services that can be bound to a container
 *
 * In this context, a service is an `interface` implemented by the class,
 * allowing it to be registered with (or "bound to") an {@see IContainer} as the
 * concrete class to instantiate when the abstract service is requested.
 *
 * Containers resolve service/`interface` names to instances:
 * - when they are requested explicitly via {@see IContainer::get()}, and
 * - when they are used as type hints in the constructors of dependencies
 *   encountered while resolving a call to {@see IContainer::get()}.
 *
 * Implement {@see IServiceSingleton} if the class should only be instantiated
 * once and/or {@see IServiceShared} to request creation of one instance per
 * service, or {@see IService} to always create a new instance.
 */
interface IService
{
    /**
     * Get a list of services provided by the class
     *
     * @return string[]
     */
    public static function getServices(): array;

    /**
     * Get a dependency subtitution map for the class
     *
     * Return an array that maps class names to compatible replacements. The
     * container resolves mapped classes to their respective substitutes:
     * - when resolving the class's dependencies, and
     * - when using {@see IContainer::inContextOf()} to work with a container in
     *   the context of the class.
     *
     * @return array<string,string>
     */
    public static function getContextualBindings(): array;

}
