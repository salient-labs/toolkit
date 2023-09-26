<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Builder;

/**
 * Returns the name of a builder that creates instances of the class via a
 * fluent interface
 *
 * @template TBuilder of Builder
 *
 * @see Builder
 */
interface ReturnsBuilderService
{
    /**
     * Get the name of a builder that creates instances of the class via a
     * fluent interface
     *
     * @return class-string<TBuilder>
     */
    public static function getBuilder(): string;
}
