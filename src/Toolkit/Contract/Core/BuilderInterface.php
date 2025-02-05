<?php declare(strict_types=1);

namespace Salient\Contract\Core;

/**
 * @api
 *
 * @template TClass of object
 */
interface BuilderInterface extends Chainable, Immutable
{
    /**
     * Get a new builder
     *
     * @return static
     */
    public static function create();

    /**
     * Get an instance from a possibly-terminated builder
     *
     * @param static|TClass $object
     * @return TClass
     */
    public static function resolve($object);

    /**
     * Terminate the builder by creating an instance
     *
     * @return TClass
     */
    public function build();
}
