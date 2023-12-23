<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Contract\IService;
use Lkrms\Contract\IServiceShared;
use Lkrms\Contract\IServiceSingleton;

/**
 * Implements IService to provide services that can be bound to a container
 *
 * @see IService
 * @see IServiceSingleton
 * @see IServiceShared
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
