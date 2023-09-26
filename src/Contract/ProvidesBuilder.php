<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Builder;
use Lkrms\Concern\HasBuilder;

/**
 * Returns and resolves builders that create instances of the class via a fluent
 * interface
 *
 * @template TBuilder of Builder
 *
 * @see Builder
 * @see HasBuilder
 *
 * @extends ReturnsBuilderService<TBuilder>
 * @extends ResolvesBuilder<TBuilder>
 */
interface ProvidesBuilder extends ReturnsBuilderService, ResolvesBuilder {}
