<?php declare(strict_types=1);

namespace Salient\Contract\Core;

use Salient\Contract\Container\ContainerInterface;

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
    public static function create(?ContainerInterface $container = null);

    /**
     * Get an instance from an optionally terminated builder
     *
     * @param static|TClass $object
     * @return TClass
     */
    public static function resolve($object);

    /**
     * Get a value applied to the builder
     *
     * @return mixed
     */
    public function getB(string $name);

    /**
     * Check if a value has been applied to the builder
     */
    public function issetB(string $name): bool;

    /**
     * Remove a value applied to the builder
     *
     * @return static
     */
    public function unsetB(string $name);

    /**
     * Resolve the builder to an instance
     *
     * @return TClass
     */
    public function build();
}
