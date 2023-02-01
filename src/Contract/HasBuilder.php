<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Builder;

/**
 * Uses a builder to provide a fluent interface for creating instances
 *
 * @see Builder
 */
interface HasBuilder
{
    /**
     * Create a new instance
     *
     */
    public static function build(?IContainer $container = null): Builder;

    /**
     * Get an instance from a builder that may or may not have been terminated
     *
     * This method simplifies working with instances and unterminated
     * {@see Builder}s interchangeably.
     *
     * @param Builder|static|null $object
     * @return static
     */
    public static function resolve($object);
}
