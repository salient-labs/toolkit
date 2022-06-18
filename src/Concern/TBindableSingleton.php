<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;

trait TBindableSingleton
{
    use TBindable;

    final public static function bind(Container $container)
    {
        $container->singleton(static::class);
    }
}
