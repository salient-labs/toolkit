<?php declare(strict_types=1);

namespace Lkrms\Contract;

/**
 * Returns the name of the class or interface it was resolved from
 */
interface ReturnsService
{
    /**
     * Get the name of the class or interface the container resolved by creating
     * the instance
     *
     * @return class-string
     */
    public function service(): string;
}
