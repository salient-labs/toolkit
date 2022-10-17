<?php

declare(strict_types=1);

namespace Lkrms\Concern;

use Lkrms\Container\Container;

trait HasContainer
{
    /**
     * @var Container
     */
    private $Container;

    public function __construct(Container $container)
    {
        $this->Container = $container;
    }

    final public function app(): Container
    {
        return $this->Container;
    }

    final public function container(): Container
    {
        return $this->Container;
    }

}
