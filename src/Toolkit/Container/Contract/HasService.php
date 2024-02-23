<?php declare(strict_types=1);

namespace Salient\Container\Contract;

/**
 * Returns the name of the class or interface it was resolved from
 */
interface HasService
{
    /**
     * Get the name of the class or interface the container resolved by creating
     * the instance
     *
     * @return class-string
     */
    public function service(): string;
}
