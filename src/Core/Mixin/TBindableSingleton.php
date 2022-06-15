<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

use Lkrms\Container\Container;

/**
 * Partially implements IBindable to provide services and bind them to
 * containers
 *
 * @see \Lkrms\Core\Contract\IBindable
 */
trait TBindableSingleton
{
    /**
     * @var Container
     */
    private $Container;

    public function __construct(Container $container)
    {
        $this->Container = $container;
    }

    final public static function bind(Container $container)
    {
        $container->singleton(static::class);
    }

    final public function container(): Container
    {
        return $this->Container;
    }
}
