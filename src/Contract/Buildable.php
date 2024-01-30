<?php declare(strict_types=1);

namespace Lkrms\Contract;

use Lkrms\Concept\Builder;
use Lkrms\Concern\HasBuilder;
use Lkrms\Container\Contract\ContainerInterface;

/**
 * @template TBuilder of Builder
 *
 * @see Builder
 * @see HasBuilder
 */
interface Buildable
{
    /**
     * Get the name of a builder that creates instances of the class via a
     * fluent interface
     *
     * @return class-string<TBuilder>
     */
    public static function getBuilder(): string;

    /**
     * Get a builder that creates instances of the class via a fluent interface
     *
     * @return TBuilder
     */
    public static function build(?ContainerInterface $container = null): Builder;

    /**
     * Get an instance of the class from an optionally terminated builder
     *
     * @param TBuilder|static $object
     * @return static
     */
    public static function resolve($object);
}
