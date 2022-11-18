<?php

declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Implements IService to provide services that can be bound to a container
 *
 * @see \Lkrms\Contract\IService
 * @see \Lkrms\Contract\IServiceSingleton
 */
trait TService
{
    public static function getServices(): array
    {
        return [];
    }

    public static function getContextualBindings(): array
    {
        return [];
    }

}
