<?php

declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Builder;

/**
 * Returns a builder that provides a fluent interface for creating instances
 *
 * @see Builder
 */
interface HasBuilder
{
    /**
     * Use a fluent interface to create a new instance
     *
     */
    public static function build(): Builder;

    /**
     * Resolve a builder or an instance to an instance
     *
     * Makes it easy to work with instances and unterminated {@see Builder}s
     * that can create them interchangeably.
     *
     * @param Builder|static|null $object
     * @return static
     */
    public static function resolve($object);

}
