<?php declare(strict_types=1);

namespace Salient\Contract\Container;

/**
 * @api
 */
interface HasServices
{
    /**
     * Get services provided by the class
     *
     * @return class-string[]
     */
    public static function getServices(): array;
}
