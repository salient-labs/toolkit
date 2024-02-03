<?php declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Contract\HasContextualBindings;
use Lkrms\Container\Contract\HasServices;
use Lkrms\Container\Contract\ServiceSingletonInterface;
use Lkrms\Container\Contract\SingletonInterface;

/**
 * Implements HasServices and HasContextualBindings
 *
 * @see HasServices
 * @see HasContextualBindings
 * @see SingletonInterface
 * @see ServiceSingletonInterface
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
