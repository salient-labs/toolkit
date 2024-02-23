<?php declare(strict_types=1);

namespace Salient\Core\Contract;

use Salient\Container\ContainerInterface;
use Salient\Core\Concern\HasBuilder;
use Salient\Core\AbstractBuilder;

/**
 * @template TBuilder of AbstractBuilder
 *
 * @see AbstractBuilder
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
    public static function build(?ContainerInterface $container = null): AbstractBuilder;

    /**
     * Get an instance of the class from an optionally terminated builder
     *
     * @param TBuilder|static $object
     * @return static
     */
    public static function resolve($object);
}
