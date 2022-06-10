<?php

declare(strict_types=1);

namespace Lkrms\Core\Mixin;

use Psr\Container\ContainerInterface as Container;

/**
 * Implements IBound to bind instances to their containers of origin
 *
 * @see \Lkrms\Core\Contract\IBound
 */
trait TBound
{
    /**
     * @var Container
     */
    private $Container;

    public function __construct(Container $container)
    {
        $this->Container = $container;
    }

    final public function container(): Container
    {
        return $this->Container;
    }

}
