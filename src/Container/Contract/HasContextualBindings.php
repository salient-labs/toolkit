<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Implemented by service providers with container bindings to register in the
 * context of the class
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
