<?php declare(strict_types=1);

namespace Lkrms\Container\Contract;

/**
 * Implemented by service providers with container bindings
 *
 * @api
 */
interface HasBindings
{
    /**
     * Get bindings to register with a container
     *
     * @return array<class-string,class-string>
     */
    public static function getBindings(): array;

    /**
     * Get shared bindings to register with a container
     *
     * @return array<class-string|int,class-string>
     */
    public static function getSingletons(): array;
}
