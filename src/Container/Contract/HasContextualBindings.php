<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

interface HasContextualBindings
{
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
