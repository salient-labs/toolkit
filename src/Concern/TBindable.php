<?php

declare(strict_types=1);

namespace Lkrms\Concern;

/**
 * Implements IBindable to provide services that can be bound to a container
 *
 * @see \Lkrms\Contract\IBindable
 * @see \Lkrms\Contract\IBindableSingleton
 */
trait TBindable
{
    public static function getBindable(): array
    {
        return [];
    }

    public static function getBindings(): array
    {
        return [];
    }

}
