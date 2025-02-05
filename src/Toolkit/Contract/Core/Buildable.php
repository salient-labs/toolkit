<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 *
 * @template TBuilder of BuilderInterface
 */
interface Buildable
{
    /**
     * Get a builder for the class
     *
     * @return TBuilder
     */
    public static function build(): BuilderInterface;

    /**
     * Get an instance of the class from a possibly-terminated builder
     *
     * @param TBuilder|static $object
     * @return static
     */
    public static function resolve($object);
}
