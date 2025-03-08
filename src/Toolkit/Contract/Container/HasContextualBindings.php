<?php declare(strict_types=1);

namespace Salient\Contract\Container;

use Closure;

/**
 * @api
 */
interface HasContextualBindings
{
    /**
     * Get bindings to add to the container in the context of the class
     *
     * The contextual bindings of the class apply:
     *
     * - when resolving its dependencies
     * - when using {@see ContainerInterface::inContextOf()} to work with the
     *   container in the context of the class
     *
     * @return array<class-string|non-empty-string|int,(Closure(ContainerInterface): object)|class-string|object>
     */
    public static function getContextualBindings(ContainerInterface $container): array;
}
