<?php

declare(strict_types=1);

namespace Lkrms\Container;

use Psr\Container\ContainerInterface;

/**
 * A naive object factory that implements PSR-11
 *
 * Intended only for contexts where a `ContainerInterface` is required but
 * calling `new $class()` would suffice.
 */
final class FactoryContainer implements ContainerInterface
{
    /**
     * Get an instance of the given class
     *
     * @return mixed
     */
    public function get(string $id, ...$params)
    {
        return new $id(...$params);
    }

    /**
     * Returns true if the given class exists
     */
    public function has(string $id): bool
    {
        return class_exists($id);
    }
}
