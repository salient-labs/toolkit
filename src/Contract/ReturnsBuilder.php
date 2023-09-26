<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Builder;

/**
 * Returns builders that create instances of the class via a fluent interface
 *
 * @template TBuilder of Builder
 *
 * @see Builder
 */
interface ReturnsBuilder
{
    /**
     * Get a builder that creates instances of the class via a fluent interface
     *
     * @return TBuilder
     */
    public static function build(?IContainer $container = null): Builder;
}
