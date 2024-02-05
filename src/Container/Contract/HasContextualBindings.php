<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

use Lkrms\Container\ContainerInterface;

/**
 * Implemented by service providers with container bindings that only apply in
 * the context of the class
 *
 * @api
 */
interface HasContextualBindings
{
    /**
     * Get bindings to register with a container in the context of the class
     *
     * Bindings returned by this method apply:
     *
     * - when resolving the class's dependencies
     * - when using {@see ContainerInterface::inContextOf()} to work with a
     *   container in the context of the class
     *
     * @return array<class-string,class-string>
     */
    public static function getContextualBindings(): array;
}
