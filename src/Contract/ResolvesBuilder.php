<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Builder;

/**
 * Resolves builders to instances
 *
 * @template TBuilder of Builder
 * @extends ReturnsBuilder<TBuilder>
 *
 * @see Builder
 */
interface ResolvesBuilder extends ReturnsBuilder
{
    /**
     * Get an instance of the class from an optionally terminated builder
     *
     * @param TBuilder|static $object
     * @return static
     */
    public static function resolve($object);
}
