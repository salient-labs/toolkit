<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Salient\Contract\Container\ContainerInterface;

/**
 * @api
 *
 * @template TBuilder of BuilderInterface
 */
interface Buildable
{
    /**
     * Get a builder that creates instances of the class
     *
     * @return TBuilder
     */
    public static function build(?ContainerInterface $container = null): BuilderInterface;

    /**
     * Get an instance of the class from an optionally terminated builder
     *
     * @param TBuilder|static $object
     * @return static
     */
    public static function resolve($object);
}
