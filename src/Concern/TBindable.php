<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;

/**
 * Implements IBindable to provide services that can be bound to a container
 *
 * @see \Lkrms\Contract\IBindable
 * @see \Lkrms\Contract\IBindableSingleton
 */
trait TBindable
{
    /**
     * @var Container
     */
    private $Container;

    public function __construct(Container $container)
    {
        $this->Container = $container;
    }

    public static function getBindable(): array
    {
        return [];
    }

    public static function getBindings(): array
    {
        return [];
    }

    final public function container(): Container
    {
        return $this->Container;
    }
}
