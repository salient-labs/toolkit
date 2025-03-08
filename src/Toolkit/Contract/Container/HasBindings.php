<?php declare(strict_types=1);

namespace Salient\Contract\Container;

use Closure;

/**
 * @api
 */
interface HasBindings
{
    /**
     * Get services to bind to the container
     *
     * @return array<class-string,(Closure(ContainerInterface): object)|class-string>
     */
    public static function getBindings(ContainerInterface $container): array;

    /**
     * Get shared services to bind to the container
     *
     * @return array<class-string|int,(Closure(ContainerInterface): object)|class-string>
     */
    public static function getSingletons(ContainerInterface $container): array;
}
